<?php

declare(strict_types=1);

namespace App\Booking\Infrastructure\Persistence;

use App\Booking\Domain\ContactEmail;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class ContactEmailType extends Type
{
    public const string NAME = 'contact_email';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] ??= 255;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof ContactEmail) {
            throw InvalidType::new($value, self::NAME, ['null', ContactEmail::class]);
        }

        return $value->value();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ContactEmail
    {
        if (null === $value || $value instanceof ContactEmail) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, ContactEmail::class, ['null', 'string', ContactEmail::class]);
        }

        return ContactEmail::of($value);
    }
}
