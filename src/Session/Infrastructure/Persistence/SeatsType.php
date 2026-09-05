<?php

declare(strict_types=1);

namespace App\Session\Infrastructure\Persistence;

use App\Session\Domain\Seats;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class SeatsType extends Type
{
    public const string NAME = 'seats';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getIntegerTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Seats) {
            throw InvalidType::new($value, self::NAME, ['null', Seats::class]);
        }

        return $value->value();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Seats
    {
        if (null === $value || $value instanceof Seats) {
            return $value;
        }

        if (!\is_int($value)) {
            throw InvalidType::new($value, Seats::class, ['null', 'int', Seats::class]);
        }

        if (0 === $value) {
            return Seats::none();
        }

        return Seats::of($value);
    }
}
