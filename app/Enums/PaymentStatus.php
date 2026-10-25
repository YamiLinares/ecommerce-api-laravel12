<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Canceled = 'canceled';

    /** Un pago abierto todavía puede completarse en Stripe. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Processing], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Succeeded, self::Canceled], true);
    }
}
