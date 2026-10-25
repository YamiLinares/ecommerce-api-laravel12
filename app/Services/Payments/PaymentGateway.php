<?php

namespace App\Services\Payments;

/**
 * Contrato del proveedor de pagos. Permite sustituir Stripe por un doble en pruebas.
 */
interface PaymentGateway
{
    public function isConfigured(): bool;

    /**
     * @param  array<string, string>  $metadata
     */
    public function createPaymentIntent(
        int $amountCents,
        string $currency,
        array $metadata,
        string $idempotencyKey,
        ?string $paymentMethod = null,
    ): PaymentIntentData;

    public function retrievePaymentIntent(string $id): PaymentIntentData;

    public function confirmPaymentIntent(string $id, string $paymentMethod): PaymentIntentData;

    public function cancelPaymentIntent(string $id): PaymentIntentData;
}
