<?php

namespace App\Services\Payments;

/**
 * Datos mínimos de un PaymentIntent que la aplicación necesita (sin datos de tarjeta).
 */
final class PaymentIntentData
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly ?string $clientSecret,
        public readonly int $amount,
        public readonly string $currency,
        public readonly ?string $lastErrorMessage = null,
    ) {}
}
