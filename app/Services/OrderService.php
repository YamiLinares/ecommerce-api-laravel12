<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /** Códigos MySQL: 1205 = lock wait timeout, 1213 = deadlock. */
    private const LOCK_ERRORS = [1205, 1213];

    /** Importe máximo que Stripe acepta por cobro (999,999.99 en USD). */
    private const MAX_TOTAL_CENTS = 99_999_999;

    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * Crea una orden reservando stock de forma atómica.
     * Los precios se toman siempre de la base de datos, nunca del cliente.
     *
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    public function create(User $user, array $items): Order
    {
        $quantities = [];
        foreach ($items as $item) {
            $quantities[(int) $item['product_id']] = (int) $item['quantity'];
        }
        // Orden fijo de bloqueo para minimizar interbloqueos entre compras simultáneas.
        ksort($quantities);

        try {
            $order = DB::transaction(function () use ($user, $quantities) {
                $products = Product::query()
                    ->whereIn('id', array_keys($quantities))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $lines = [];
                $subtotalCents = 0;

                foreach ($quantities as $productId => $quantity) {
                    /** @var Product|null $product */
                    $product = $products->get($productId);

                    if ($product === null || ! $product->is_active) {
                        throw ApiException::productUnavailable($productId);
                    }

                    if ($product->stock < $quantity) {
                        throw ApiException::insufficientStock($product->id, $product->name, $product->stock, $quantity);
                    }

                    $unitCents = Money::toCents($product->price);
                    $lineCents = $unitCents * $quantity;
                    $subtotalCents += $lineCents;

                    $lines[] = [$product, $quantity, $unitCents, $lineCents];
                }

                if ($subtotalCents > self::MAX_TOTAL_CENTS) {
                    throw ApiException::orderTotalTooLarge();
                }

                $order = Order::create([
                    'user_id' => $user->id,
                    'status' => OrderStatus::Pending,
                    'subtotal' => Money::fromCents($subtotalCents),
                    'total' => Money::fromCents($subtotalCents),
                    'currency' => config('services.stripe.currency'),
                ]);

                foreach ($lines as [$product, $quantity, $unitCents, $lineCents]) {
                    $order->items()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'unit_price' => Money::fromCents($unitCents),
                        'quantity' => $quantity,
                        'subtotal' => Money::fromCents($lineCents),
                    ]);

                    $product->decrement('stock', $quantity);
                }

                return $order;
            }, attempts: 3);
        } catch (QueryException $e) {
            if (in_array($e->errorInfo[1] ?? null, self::LOCK_ERRORS, true)) {
                throw ApiException::inventoryBusy();
            }

            throw $e;
        }

        return $order->load('items');
    }

    /**
     * Cancela una orden pendiente y devuelve el stock reservado.
     * Antes cancela en Stripe los pagos abiertos para evitar cobrar una orden cancelada.
     */
    public function cancel(Order $order): Order
    {
        if ($order->status !== OrderStatus::Pending) {
            throw ApiException::invalidTransition($order->status->value, OrderStatus::Cancelled->value);
        }

        $openPayments = $order->payments()
            ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Processing, PaymentStatus::Failed])
            ->get();

        foreach ($openPayments as $payment) {
            $this->cancelRemotePayment($payment);
        }

        DB::transaction(function () use ($order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Pending) {
                throw ApiException::invalidTransition($locked->status->value, OrderStatus::Cancelled->value);
            }

            $items = $locked->items()->orderBy('product_id')->get();
            $products = Product::withTrashed()
                ->whereIn('id', $items->pluck('product_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $products[$item->product_id]?->increment('stock', $item->quantity);
            }

            $locked->payments()
                ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Processing, PaymentStatus::Failed])
                ->update(['status' => PaymentStatus::Canceled]);

            $locked->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
        });

        return $order->refresh()->load('items');
    }

    /**
     * Cambio manual de estado por un administrador (paid solo lo asigna el webhook).
     */
    public function transition(Order $order, OrderStatus $next): Order
    {
        if ($next === OrderStatus::Cancelled) {
            return $this->cancel($order);
        }

        return DB::transaction(function () use ($order, $next) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($next === OrderStatus::Paid || ! $locked->status->canTransitionTo($next)) {
                throw ApiException::invalidTransition($locked->status->value, $next->value);
            }

            $locked->update(['status' => $next]);

            return $locked->load('items');
        });
    }

    private function cancelRemotePayment(Payment $payment): void
    {
        if ($payment->stripe_payment_intent_id === null) {
            return;
        }

        $intent = $this->gateway->retrievePaymentIntent($payment->stripe_payment_intent_id);

        if (in_array($intent->status, ['succeeded', 'processing'], true)) {
            throw ApiException::paymentInProgress();
        }

        if ($intent->status !== 'canceled') {
            $this->gateway->cancelPaymentIntent($payment->stripe_payment_intent_id);
        }
    }
}
