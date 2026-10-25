<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\StripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Integración del cliente oficial stripe/stripe-php contra stripe-mock
 * (servidor simulado oficial de Stripe). No usa credenciales reales.
 *
 * Ejecutar stripe-mock: stripe-mock -http-port 12111
 * Si no está disponible, las pruebas se marcan como omitidas (no aprobadas).
 */
#[Group('stripe-mock')]
class StripeGatewayIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const MOCK_URL = 'http://127.0.0.1:12111';

    private StripePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $socket = @fsockopen('127.0.0.1', 12111, $errno, $errstr, 0.5);
        if ($socket === false) {
            $this->markTestSkipped('stripe-mock no está en ejecución en 127.0.0.1:12111.');
        }
        fclose($socket);

        // stripe-mock acepta cualquier clave con formato sk_test_.
        $this->gateway = new StripePaymentGateway('sk_test_123', self::MOCK_URL);
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    public function test_gateway_creates_retrieves_and_cancels_payment_intents(): void
    {
        $created = $this->gateway->createPaymentIntent(19430, 'usd', ['order_id' => '1'], 'payment-test-1');

        $this->assertStringStartsWith('pi_', $created->id);
        $this->assertNotEmpty($created->clientSecret);

        $retrieved = $this->gateway->retrievePaymentIntent($created->id);
        $this->assertStringStartsWith('pi_', $retrieved->id);

        $canceled = $this->gateway->cancelPaymentIntent($created->id);
        $this->assertStringStartsWith('pi_', $canceled->id);
    }

    public function test_payment_endpoint_works_end_to_end_with_stripe_client(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'subtotal' => '25.00', 'total' => '25.00']);

        $this->postJson("/api/orders/{$order->id}/payments", [], $this->jwtHeaders($user))
            ->assertCreated()
            ->assertJsonPath('data.payment.amount', '25.00')
            ->assertJsonPath('data.payment.stripe_payment_intent_id', fn ($id) => str_starts_with($id, 'pi_'));
    }

    public function test_invalid_stripe_request_is_reported_as_gateway_error(): void
    {
        $this->expectExceptionMessage('No fue posible comunicarse con el proveedor de pagos');

        // stripe-mock valida los parámetros contra la especificación OpenAPI de Stripe.
        $this->gateway->retrievePaymentIntent('id-con-formato-invalido-#');
    }
}
