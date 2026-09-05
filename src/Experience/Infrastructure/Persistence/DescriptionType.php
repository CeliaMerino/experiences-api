<?php

declare(strict_types=1);

namespace App\Experience\Infrastructure\Persistence;

use App\Experience\Domain\Description;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class DescriptionType extends Type
{
    public const string NAME = 'description';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Description) {
            throw InvalidType::new($value, self::NAME, ['null', Description::class]);
        }

        return $value->value();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Description
    {
        if (null === $value || $value instanceof Description) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, Description::class, ['null', 'string', Description::class]);
        }

        return Description::of($value);
    }
}
