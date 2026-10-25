<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Aritmética monetaria exacta en centavos (enteros); nunca usa float.
 */
final class Money
{
    /** Convierte "19.99" (string DECIMAL de MySQL) a 1999 centavos. */
    public static function toCents(string|int $amount): int
    {
        $amount = (string) $amount;

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Importe monetario inválido.');
        }

        return (int) bcmul($amount, '100', 0);
    }

    /** Convierte 1999 centavos a "19.99". */
    public static function fromCents(int $cents): string
    {
        return bcdiv((string) $cents, '100', 2);
    }
}
