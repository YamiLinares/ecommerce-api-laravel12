<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentIntentData;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    private const MIN_AMOUNT_CENTS = 50;

    /** Pagos cuyo PaymentIntent todavía puede completarse y por tanto se reutilizan. */
    private const REUSABLE = [PaymentStatus::Pending, PaymentStatus::Processing, PaymentStatus::Failed];

    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * Inicia (o reutiliza) el PaymentIntent de una orden pendiente.
     * El importe se calcula en el servidor a partir del total de la orden.
     *
     * @return array{payment: Payment, client_secret: ?string, created: bool}
     */
    public function initiate(Order $order, ?string $paymentMethod = null): array
    {
        if (! $this->gateway->isConfigured()) {
            throw ApiException::paymentsNotConfigured();
        }

        return DB::transaction(function () use ($order, $paymentMethod) {
            // El bloqueo evita crear dos PaymentIntents para la misma orden en paralelo.
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Pending) {
                throw ApiException::orderNotPayable($locked->status->value);
            }

            $amountCents = Money::toCents($locked->total);
            if ($amountCents < self::MIN_AMOUNT_CENTS) {
                throw ApiException::amountTooSmall();
            }

            $existing = $locked->payments()
                ->whereIn('status', self::REUSABLE)
                ->whereNotNull('stripe_payment_intent_id')
                ->latest('id')
                ->first();

            if ($existing !== null) {
                $intent = $this->gateway->retrievePaymentIntent($existing->stripe_payment_intent_id);

                if ($paymentMethod !== null && $this->isConfirmable($intent)) {
                    $intent = $this->gateway->confirmPaymentIntent($intent->id, $paymentMethod);
                }

                $this->syncFromIntent($existing, $intent);

                if ($existing->status !== PaymentStatus::Canceled) {
                    return ['payment' => $existing, 'client_secret' => $intent->clientSecret, 'created' => false];
                }
            }

            $payment = Payment::create([
                'order_id' => $locked->id,
                'user_id' => $locked->user_id,
                'provider' => 'stripe',
                'amount' => $locked->total,
                'currency' => $locked->currency,
                'status' => PaymentStatus::Pending,
            ]);

            $intent = $this->gateway->createPaymentIntent(
                $amountCents,
                $locked->currency,
                [
                    'order_id' => (string) $locked->id,
                    'user_id' => (string) $locked->user_id,
                    'payment_id' => (string) $payment->id,
                ],
                'payment-'.$payment->id,
                $paymentMethod,
            );

            $payment->stripe_payment_intent_id = $intent->id;
            $this->syncFromIntent($payment, $intent);

            return ['payment' => $payment, 'client_secret' => $intent->clientSecret, 'created' => true];
        });
    }

    private function isConfirmable(PaymentIntentData $intent): bool
    {
        return in_array($intent->status, ['requires_payment_method', 'requires_confirmation'], true);
    }

    /**
     * Refleja el estado informado por la API de Stripe, pero nunca marca el pago como
     * completado: "succeeded" solo se aplica al recibir el webhook firmado.
     */
    private function syncFromIntent(Payment $payment, PaymentIntentData $intent): void
    {
        $payment->status = match ($intent->status) {
            'processing', 'succeeded' => PaymentStatus::Processing,
            'canceled' => PaymentStatus::Canceled,
            default => $intent->lastErrorMessage !== null ? PaymentStatus::Failed : PaymentStatus::Pending,
        };
        $payment->failure_message = $intent->lastErrorMessage !== null
            ? mb_substr($intent->lastErrorMessage, 0, 255)
            : null;
        $payment->save();
    }
}
