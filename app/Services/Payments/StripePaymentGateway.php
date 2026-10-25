<?php

namespace App\Services\Payments;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

/**
 * Implementación con la biblioteca oficial stripe/stripe-php (PaymentIntents).
 */
class StripePaymentGateway implements PaymentGateway
{
    private ?StripeClient $client = null;

    /** $apiBase solo se usa en pruebas para apuntar a stripe-mock; en producción es null. */
    public function __construct(
        private readonly string $secretKey,
        private readonly ?string $apiBase = null,
    ) {}

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    public function createPaymentIntent(
        int $amountCents,
        string $currency,
        array $metadata,
        string $idempotencyKey,
        ?string $paymentMethod = null,
    ): PaymentIntentData {
        $params = [
            'amount' => $amountCents,
            'currency' => $currency,
            'metadata' => $metadata,
            'description' => 'Orden #'.($metadata['order_id'] ?? ''),
            // Sin redirecciones: la API se prueba sin frontend (tarjetas de prueba).
            'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
        ];

        if ($paymentMethod !== null) {
            $params['payment_method'] = $paymentMethod;
            $params['confirm'] = true;
        }

        return $this->call('create', fn (StripeClient $s) => $s->paymentIntents->create(
            $params,
            ['idempotency_key' => $idempotencyKey],
        ));
    }

    public function retrievePaymentIntent(string $id): PaymentIntentData
    {
        return $this->call('retrieve', fn (StripeClient $s) => $s->paymentIntents->retrieve($id));
    }

    public function confirmPaymentIntent(string $id, string $paymentMethod): PaymentIntentData
    {
        return $this->call('confirm', fn (StripeClient $s) => $s->paymentIntents->confirm($id, [
            'payment_method' => $paymentMethod,
        ]));
    }

    public function cancelPaymentIntent(string $id): PaymentIntentData
    {
        return $this->call('cancel', fn (StripeClient $s) => $s->paymentIntents->cancel($id));
    }

    /**
     * @param  callable(StripeClient): PaymentIntent  $callback
     */
    private function call(string $operation, callable $callback): PaymentIntentData
    {
        if (! $this->isConfigured()) {
            throw ApiException::paymentsNotConfigured();
        }

        try {
            return $this->toData($callback($this->client()));
        } catch (ApiErrorException $e) {
            // Las tarjetas rechazadas al confirmar devuelven el PaymentIntent dentro del error.
            $intent = $e->getError()?->payment_intent ?? null;
            if ($intent instanceof PaymentIntent) {
                return $this->toData($intent);
            }

            // Se registra solo información no sensible (sin claves ni datos de tarjeta).
            Log::warning('Error al comunicarse con Stripe', [
                'operation' => $operation,
                'stripe_code' => $e->getStripeCode(),
                'http_status' => $e->getHttpStatus(),
                'request_id' => $e->getRequestId(),
            ]);

            throw ApiException::paymentGateway();
        }
    }

    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient(array_filter([
            'api_key' => $this->secretKey,
            'api_base' => $this->apiBase,
            'max_network_retries' => 2,
        ]));
    }

    private function toData(PaymentIntent $intent): PaymentIntentData
    {
        return new PaymentIntentData(
            id: $intent->id,
            status: $intent->status,
            clientSecret: $intent->client_secret,
            amount: (int) $intent->amount,
            currency: (string) $intent->currency,
            lastErrorMessage: $intent->last_payment_error?->message,
        );
    }
}
