<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public static function amounts(): array
    {
        return [
            ['19.99', 1999],
            ['0.10', 10],
            ['0.1', 10],
            ['100', 10000],
            ['899.00', 89900],
            ['99999999.99', 9999999999],
        ];
    }

    #[DataProvider('amounts')]
    public function test_converts_decimal_strings_to_cents_exactly(string $amount, int $cents): void
    {
        $this->assertSame($cents, Money::toCents($amount));
    }

    public function test_avoids_floating_point_errors(): void
    {
        // Con float: 0.1 + 0.2 = 0.30000000000000004
        $sum = Money::toCents('0.10') + Money::toCents('0.20');

        $this->assertSame('0.30', Money::fromCents($sum));
        $this->assertSame('60.27', Money::fromCents(Money::toCents('19.99') * 3 + Money::toCents('0.10') * 3));
    }

    public function test_rejects_invalid_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::toCents('10.999');
    }
}
