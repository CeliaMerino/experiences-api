<?php

declare(strict_types=1);

namespace App\Tests\Shared\Unit;

use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testMultiplySobre1250EurPor3Devuelve3750Eur(): void
    {
        $money = Money::of(1250, 'EUR');

        $result = $money->multiply(3);

        self::assertTrue(Money::of(3750, 'EUR')->equals($result));
    }

    public function testImporteNegativoLanzaInvalidValue(): void
    {
        $this->expectException(InvalidValue::class);

        Money::of(-1, 'EUR');
    }

    public function testDivisaMalFormadaLanzaInvalidValue(): void
    {
        $this->expectException(InvalidValue::class);

        Money::of(100, 'EU');
    }

    public function testDivisaEnMinusculasLanzaInvalidValue(): void
    {
        $this->expectException(InvalidValue::class);

        Money::of(100, 'eur');
    }

    public function testEqualsEsCiertoSoloConMismoImporteYDivisa(): void
    {
        $a = Money::of(1250, 'EUR');
        $b = Money::of(1250, 'EUR');
        $c = Money::of(1250, 'USD');
        $d = Money::of(1000, 'EUR');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
        self::assertFalse($a->equals($d));
    }

    public function testFactorNegativoEnMultiplyLanzaInvalidValue(): void
    {
        $this->expectException(InvalidValue::class);

        Money::of(1250, 'EUR')->multiply(-1);
    }
}
