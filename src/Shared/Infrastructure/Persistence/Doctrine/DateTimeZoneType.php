<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

final class DateTimeZoneType extends Type
{
    public const string NAME = 'date_time_zone';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] ??= 64;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof \DateTimeZone) {
            throw InvalidType::new($value, self::NAME, ['null', \DateTimeZone::class]);
        }

        return $value->getName();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeZone
    {
        if (null === $value || $value instanceof \DateTimeZone) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, \DateTimeZone::class, ['null', 'string', \DateTimeZone::class]);
        }

        try {
            return new \DateTimeZone($value);
        } catch (\Exception $exception) {
            throw ValueNotConvertible::new($value, \DateTimeZone::class, previous: $exception);
        }
    }
}
