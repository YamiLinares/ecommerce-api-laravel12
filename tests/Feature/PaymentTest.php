<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
        $this->customer = User::factory()->create();
    }

    private function pendingOrder(string $total = '194.30'): Order
    {
        return Order::factory()->create(['user_id' => $this->customer->id, 'subtotal' => $total, 'total' => $total]);
    }

    public function test_customer_initiates_payment_with_server_side_amount(): void
    {
        $order = $this->pendingOrder('194.30');

        $this->postJson("/api/orders/{$order->id}/payments", ['amount' => 1], $this->jwtHeaders($this->customer))
            ->assertCreated()
            ->assertJsonPath('data.payment.status', 'pending')
            ->assertJsonPath('data.payment.amount', '194.30')
            ->assertJsonPath('data.payment.currency', 'usd')
            ->assertJsonPath('data.payment.stripe_payment_intent_id', 'pi_fake_1')
            ->assertJsonPath('data.client_secret', 'pi_fake_1_secret_test');

        $call = $this->gateway->calls[0];
        $this->assertSame(19430, $call['amount']);
        $this->assertSame('usd', $call['currency']);
        $this->assertSame((string) $order->id, $call['metadata']['order_id']);
        $this->assertSame((string) $this->customer->id, $call['metadata']['user_id']);
        $this->assertStringStartsWith('payment-', $call['idempotency_key']);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'stripe_payment_intent_id' => 'pi_fake_1',
            'amount' => '194.30',
        ]);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_repeated_initiation_reuses_open_payment_intent(): void
    {
        $order = $this->pendingOrder();

        $this->postJson("/api/orders/{$order->id}/payments", [], $this->jwtHeaders($this->customer))->assertCreated();
        $this->resetAuth();
        $this->postJson("/api/orders/{$order->id}/payments", [], $this->jwtHeaders($this->customer))
            ->assertOk()
            ->assertJsonPath('data.payment.stripe_payment_intent_id', 'pi_fake_1');

        $this->assertSame(1, Payment::count());
        $this->assertSame(['create', 'retrieve'], $this->gateway->operations());
    }

    public function test_server_side_confirmation_does_not_mark_order_paid_without_webhook(): void
    {
        $order = $this->pendingOrder();

        $this->postJson("/api/orders/{$order->id}/payments", ['payment_method' => 'pm_card_visa'], $this->jwtHeaders($this->customer))
            ->assertCreated()
            ->assertJsonPath('data.payment.status', 'processing');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_declined_card_is_recorded_without_card_data(): void
    {
        $order = $this->pendingOrder();
        $this->gateway->confirmError = 'Your card was declined.';

        $this->postJson("/api/orders/{$order->id}/payments", ['payment_method' => 'pm_card_chargeDeclined'], $this->jwtHeaders($this->customer))
            ->assertCreated()
            ->assertJsonPath('data.payment.status', 'failed')
            ->assertJsonPath('data.payment.failure_message', 'Your card was declined.');

        // Reintento con otra tarjeta sobre el mismo PaymentIntent.
        $this->gateway->confirmError = null;
        $this->resetAuth();
        $this->postJson("/api/orders/{$order->id}/payments", ['payment_method' => 'pm_card_visa'], $this->jwtHeaders($this->customer))
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'processing');

        $this->assertSame(1, Payment::count());
    }

    public function test_payment_method_must_be_a_stripe_payment_method_id(): void
    {
        $order = $this->pendingOrder();

        $this->postJson("/api/orders/{$order->id}/payments", ['payment_method' => '4242424242424242'], $this->jwtHeaders($this->customer))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);

        $this->assertSame([], $this->gateway->calls);
    }

    public function test_only_pending_orders_can_be_paid(): void
    {
        $order = Order::factory()->status(OrderStatus::Paid)->create(['user_id' => $this->customer->id]);

        $this->postJson("/api/orders/{$order->id}/payments", [], $this->jwtHeaders($this->customer))
            ->assertStatus(409)
            ->assertJsonPath('code', 'ORDER_NOT_PAYABLE');
    }

    public function test_admin_cannot_pay_on_behalf_of_customer(): void
    {
        $order = $this->pendingOrder();

        $this->postJson("/api/orders/{$order->id}/payments", [], $this->jwtHeaders(User::factory()->admin()->create()))
            ->assertNotFound();
    }

    public function test_payment_requires_authentication(): void
    {
        $order = $this->pendingOrder();

        $this->postJson("/api/orders/{$order->id}/payments")->assertUnauthorized();
    }

    public function test_stripe_communication_error_returns_502(): void
    {
        $order = $this->pendingOrder();
        $this->gateway->failNetwork = true;

        $this->postJson("/api/orders/{$order->id}/payments", [], $this->jwtHeaders($this->customer))
            ->assertStatus(502)
            ->assertExactJson([
                'message' => 'No fue posible comunicarse con el proveedor de pagos. Intente más tarde.',
                'code' => 'PAYMENT_GATEWAY_ERROR',
            ]);

        // La transacción se revierte: no quedan pagos huérfanos.
        $this->assertSame(0, Payment::count());
    }

    public function test_unconfigured_stripe_returns_503(): void
    {
        $order = $this->pendingOrder();
        $this->gateway->configured = false;

        $this->postJson("/api/orders/{$order->id}/payments", [], $this->jwtHeaders($this->customer))
            ->assertStatus(503)
            ->assertJsonPath('code', 'PAYMENTS_NOT_CONFIGURED');
    }

    public function test_amount_below_stripe_minimum_is_rejected(): void
    {
        $order = $this->pendingOrder('0.30');

        $this->postJson("/api/orders/{$order->id}/payments", [], $this->jwtHeaders($this->customer))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'AMOUNT_TOO_SMALL');
    }

    public function test_customer_lists_payments_of_own_order(): void
    {
        $order = $this->pendingOrder();
        $this->postJson("/api/orders/{$order->id}/payments", [], $this->jwtHeaders($this->customer))->assertCreated();

        $this->resetAuth();
        $this->getJson("/api/orders/{$order->id}/payments", $this->jwtHeaders($this->customer))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.client_secret');
    }

    public function test_cancelling_order_cancels_open_payment_intent(): void
    {
        $product = Product::factory()->create(['stock' => 3, 'price' => '10.00']);
        $orderId = $this->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 2]]], $this->jwtHeaders($this->customer))
            ->json('data.id');
        $this->resetAuth();
        $this->postJson("/api/orders/{$orderId}/payments", [], $this->jwtHeaders($this->customer))->assertCreated();

        $this->resetAuth();
        $this->postJson("/api/orders/{$orderId}/cancel", [], $this->jwtHeaders($this->customer))
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertContains('cancel', $this->gateway->operations());
        $this->assertSame(PaymentStatus::Canceled, Payment::first()->status);
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_order_with_payment_in_progress_cannot_be_cancelled(): void
    {
        $order = $this->pendingOrder();
        $this->postJson("/api/orders/{$order->id}/payments", [], $this->jwtHeaders($this->customer))->assertCreated();
        $this->gateway->setStatus('pi_fake_1', 'processing');

        $this->resetAuth();
        $this->postJson("/api/orders/{$order->id}/cancel", [], $this->jwtHeaders($this->customer))
            ->assertStatus(409)
            ->assertJsonPath('code', 'PAYMENT_IN_PROGRESS');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }
}
