<?php

declare(strict_types=1);

namespace App\Tests\Shared\Unit;

use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    public function testGenerateDevuelveUuidV4ConFormatoCanonico(): void
    {
        $uuid = Uuid::generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid->value(),
        );
    }

    public function testFromStringConservaElValorNormalizado(): void
    {
        $raw = '550e8400-E29B-44D4-A716-446655440000';

        $uuid = Uuid::fromString($raw);

        self::assertSame('550e8400-e29b-44d4-a716-446655440000', $uuid->value());
    }

    public function testFromStringConFormatoInvalidoLanzaInvalidValue(): void
    {
        $this->expectException(InvalidValue::class);

        Uuid::fromString('not-a-uuid');
    }

    public function testDosGenerateDistintosNoSonEquals(): void
    {
        $a = Uuid::generate();
        $b = Uuid::generate();

        self::assertFalse($a->equals($b));
    }
}
