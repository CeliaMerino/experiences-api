<?php

declare(strict_types=1);

namespace App\Session\Infrastructure\Persistence;

use App\Session\Domain\Capacity;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class CapacityType extends Type
{
    public const string NAME = 'capacity';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getIntegerTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Capacity) {
            throw InvalidType::new($value, self::NAME, ['null', Capacity::class]);
        }

        return $value->value();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Capacity
    {
        if (null === $value || $value instanceof Capacity) {
            return $value;
        }

        if (!\is_int($value)) {
            throw InvalidType::new($value, Capacity::class, ['null', 'int', Capacity::class]);
        }

        return Capacity::of($value);
    }
}
