<?php

declare(strict_types=1);

namespace App\Booking\Infrastructure\Persistence;

use App\Booking\Domain\BookingId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class BookingIdType extends Type
{
    public const string NAME = 'booking_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof BookingId) {
            throw InvalidType::new($value, self::NAME, ['null', BookingId::class]);
        }

        return $value->value();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BookingId
    {
        if (null === $value || $value instanceof BookingId) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, BookingId::class, ['null', 'string', BookingId::class]);
        }

        return BookingId::fromString($value);
    }
}
