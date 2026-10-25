<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StripeWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Usa la verificación real de firmas de stripe/stripe-php (Webhook::constructEvent)
 * con un secreto de pruebas: no requiere red ni credenciales reales.
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_testing_only_local_secret';

    private Order $order;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => self::SECRET]);

        $this->order = Order::factory()->create(['subtotal' => '194.30', 'total' => '194.30']);
        $this->payment = Payment::create([
            'order_id' => $this->order->id,
            'user_id' => $this->order->user_id,
            'stripe_payment_intent_id' => 'pi_test_123',
            'amount' => '194.30',
            'currency' => 'usd',
            'status' => PaymentStatus::Pending,
        ]);
    }

    private function event(string $type, array $intent = [], ?string $id = null): string
    {
        return json_encode([
            'id' => $id ?? 'evt_'.Str::random(16),
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => array_merge([
                'id' => 'pi_test_123',
                'object' => 'payment_intent',
                'amount' => 19430,
                'amount_received' => $type === 'payment_intent.succeeded' ? 19430 : 0,
                'currency' => 'usd',
                'status' => 'succeeded',
                'metadata' => ['order_id' => (string) $this->order->id, 'payment_id' => (string) $this->payment->id],
            ], $intent)],
        ]);
    }

    private function sign(string $payload, ?int $timestamp = null, string $secret = self::SECRET): string
    {
        $timestamp ??= time();

        return "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
    }

    private function sendWebhook(string $payload, ?string $signature)
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($signature !== null) {
            $server['HTTP_STRIPE_SIGNATURE'] = $signature;
        }

        return $this->call('POST', '/api/webhooks/stripe', [], [], [], $server, $payload);
    }

    public function test_succeeded_event_marks_payment_and_order_as_paid(): void
    {
        $payload = $this->event('payment_intent.succeeded');

        $this->sendWebhook($payload, $this->sign($payload))
            ->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('duplicate', false);

        $this->assertSame(PaymentStatus::Succeeded, $this->payment->fresh()->status);
        $this->assertNotNull($this->payment->fresh()->paid_at);
        $this->assertSame(OrderStatus::Paid, $this->order->fresh()->status);
        $this->assertNotNull($this->order->fresh()->paid_at);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payload = $this->event('payment_intent.succeeded');

        $this->sendWebhook($payload, $this->sign($payload, secret: 'whsec_otro_secreto'))
            ->assertStatus(400)->assertJsonPath('code', 'INVALID_SIGNATURE');

        $this->sendWebhook($payload, null)->assertStatus(400)->assertJsonPath('code', 'INVALID_SIGNATURE');

        // Firma válida de un cuerpo distinto (manipulación del contenido).
        $tampered = $this->event('payment_intent.succeeded', ['amount_received' => 1]);
        $this->sendWebhook($tampered, $this->sign($payload))->assertStatus(400);

        $this->assertSame(OrderStatus::Pending, $this->order->fresh()->status);
        $this->assertSame(0, StripeWebhookEvent::count());
    }

    public function test_expired_signature_timestamp_is_rejected(): void
    {
        $payload = $this->event('payment_intent.succeeded');

        $this->sendWebhook($payload, $this->sign($payload, time() - 3600))
            ->assertStatus(400)->assertJsonPath('code', 'INVALID_SIGNATURE');

        $this->assertSame(OrderStatus::Pending, $this->order->fresh()->status);
    }

    public function test_duplicate_events_are_processed_only_once(): void
    {
        $payload = $this->event('payment_intent.succeeded', id: 'evt_duplicado_1');

        $this->sendWebhook($payload, $this->sign($payload))->assertOk()->assertJsonPath('duplicate', false);
        $paidAt = $this->order->fresh()->paid_at;

        $this->travel(5)->minutes();
        $this->sendWebhook($payload, $this->sign($payload))->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame(1, StripeWebhookEvent::where('stripe_event_id', 'evt_duplicado_1')->count());
        $this->assertEquals($paidAt, $this->order->fresh()->paid_at);
    }

    public function test_failed_event_marks_payment_failed_and_keeps_order_pending(): void
    {
        $payload = $this->event('payment_intent.payment_failed', [
            'status' => 'requires_payment_method',
            'last_payment_error' => ['message' => 'Your card was declined.'],
        ]);

        $this->sendWebhook($payload, $this->sign($payload))->assertOk();

        $this->assertSame(PaymentStatus::Failed, $this->payment->fresh()->status);
        $this->assertSame('Your card was declined.', $this->payment->fresh()->failure_message);
        $this->assertSame(OrderStatus::Pending, $this->order->fresh()->status);
    }

    public function test_processing_and_canceled_events_update_payment_status(): void
    {
        $processing = $this->event('payment_intent.processing', ['status' => 'processing']);
        $this->sendWebhook($processing, $this->sign($processing))->assertOk();
        $this->assertSame(PaymentStatus::Processing, $this->payment->fresh()->status);

        $canceled = $this->event('payment_intent.canceled', ['status' => 'canceled']);
        $this->sendWebhook($canceled, $this->sign($canceled))->assertOk();
        $this->assertSame(PaymentStatus::Canceled, $this->payment->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $this->order->fresh()->status);
    }

    public function test_late_failure_event_does_not_revert_successful_payment(): void
    {
        $success = $this->event('payment_intent.succeeded');
        $this->sendWebhook($success, $this->sign($success))->assertOk();

        $failure = $this->event('payment_intent.payment_failed', ['status' => 'requires_payment_method']);
        $this->sendWebhook($failure, $this->sign($failure))->assertOk();

        $this->assertSame(PaymentStatus::Succeeded, $this->payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $this->order->fresh()->status);
    }

    public function test_amount_mismatch_does_not_mark_order_paid(): void
    {
        $payload = $this->event('payment_intent.succeeded', ['amount' => 100, 'amount_received' => 100]);

        $this->sendWebhook($payload, $this->sign($payload))->assertOk();

        $this->assertSame(PaymentStatus::Failed, $this->payment->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $this->order->fresh()->status);
    }

    public function test_unknown_payment_intent_and_unhandled_events_are_acknowledged(): void
    {
        $unknown = $this->event('payment_intent.succeeded', ['id' => 'pi_desconocido', 'metadata' => []]);
        $this->sendWebhook($unknown, $this->sign($unknown))->assertOk();

        $other = $this->event('customer.created');
        $this->sendWebhook($other, $this->sign($other))->assertOk();

        $this->assertSame(OrderStatus::Pending, $this->order->fresh()->status);
        $this->assertSame(2, StripeWebhookEvent::count());
    }

    public function test_webhook_without_configured_secret_returns_503(): void
    {
        config(['services.stripe.webhook_secret' => '']);
        $payload = $this->event('payment_intent.succeeded');

        $this->sendWebhook($payload, $this->sign($payload))
            ->assertStatus(503)->assertJsonPath('code', 'WEBHOOK_NOT_CONFIGURED');
    }

    public function test_succeeded_event_for_cancelled_order_keeps_it_cancelled(): void
    {
        $this->order->update(['status' => OrderStatus::Cancelled]);
        $payload = $this->event('payment_intent.succeeded');

        $this->sendWebhook($payload, $this->sign($payload))->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $this->order->fresh()->status);
        $this->assertSame(PaymentStatus::Succeeded, $this->payment->fresh()->status);
    }
}
