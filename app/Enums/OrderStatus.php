<?php

namespace App\Enums;

/**
 * Estados de una orden y sus transiciones válidas.
 *
 * pending  -> paid (solo webhook de Stripe) | cancelled
 * paid     -> shipped
 * shipped  -> delivered
 * delivered y cancelled son estados finales.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Cancelled],
            self::Paid => [self::Shipped],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /** Estados que un administrador puede asignar manualmente (paid lo asigna solo Stripe). */
    public static function adminAssignable(): array
    {
        return [self::Shipped->value, self::Delivered->value, self::Cancelled->value];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
