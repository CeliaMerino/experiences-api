<?php

declare(strict_types=1);

namespace App\Session\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Money;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

final class MoneyType extends Type
{
    public const string NAME = 'money';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'JSONB';
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Money) {
            throw InvalidType::new($value, self::NAME, ['null', Money::class]);
        }

        try {
            return json_encode(
                ['amount' => $value->amount(), 'currency' => $value->currency()],
                \JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $exception) {
            throw ValueNotConvertible::new($value, self::NAME, previous: $exception);
        }
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Money
    {
        if (null === $value || $value instanceof Money) {
            return $value;
        }

        if (\is_string($value)) {
            try {
                $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw ValueNotConvertible::new($value, Money::class, previous: $exception);
            }
        }

        if (!\is_array($value)) {
            throw InvalidType::new($value, Money::class, ['null', 'string', 'array', Money::class]);
        }

        $amount = $value['amount'] ?? null;
        $currency = $value['currency'] ?? null;

        if (!\is_int($amount) || !\is_string($currency)) {
            throw ValueNotConvertible::new($value, Money::class);
        }

        return Money::of($amount, $currency);
    }
}
