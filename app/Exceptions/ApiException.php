<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Excepción de dominio que se traduce a una respuesta JSON controlada.
 * El mensaje es seguro para mostrarse al cliente.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 400,
        public readonly string $errorCode = 'BAD_REQUEST',
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function insufficientStock(int $productId, string $name, int $available, int $requested): self
    {
        return new self(
            "Stock insuficiente para el producto \"{$name}\".",
            409,
            'INSUFFICIENT_STOCK',
            ['product_id' => $productId, 'available' => $available, 'requested' => $requested],
        );
    }

    public static function productUnavailable(int $productId): self
    {
        return new self('El producto ya no está disponible.', 409, 'PRODUCT_UNAVAILABLE', ['product_id' => $productId]);
    }

    public static function inventoryBusy(): self
    {
        return new self('El inventario está siendo actualizado por otra compra. Intente nuevamente.', 409, 'INVENTORY_BUSY');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(
            "No se puede cambiar la orden de \"{$from}\" a \"{$to}\".",
            409,
            'INVALID_STATUS_TRANSITION',
            ['from' => $from, 'to' => $to],
        );
    }

    public static function orderNotPayable(string $status): self
    {
        return new self('Solo se pueden pagar órdenes pendientes.', 409, 'ORDER_NOT_PAYABLE', ['status' => $status]);
    }

    public static function paymentInProgress(): self
    {
        return new self('La orden tiene un pago en curso o completado y no puede cancelarse.', 409, 'PAYMENT_IN_PROGRESS');
    }

    public static function paymentsNotConfigured(): self
    {
        return new self('El proveedor de pagos no está configurado.', 503, 'PAYMENTS_NOT_CONFIGURED');
    }

    public static function paymentGateway(): self
    {
        return new self('No fue posible comunicarse con el proveedor de pagos. Intente más tarde.', 502, 'PAYMENT_GATEWAY_ERROR');
    }

    public static function orderTotalTooLarge(): self
    {
        return new self('El total de la orden supera el máximo permitido (999,999.99).', 422, 'ORDER_TOTAL_TOO_LARGE');
    }

    public static function amountTooSmall(): self
    {
        return new self('El importe mínimo para pagar con tarjeta es 0.50.', 422, 'AMOUNT_TOO_SMALL');
    }
}
