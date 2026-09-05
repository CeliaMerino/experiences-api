<?php

declare(strict_types=1);

namespace App\Experience\Infrastructure\Persistence;

use App\Experience\Domain\Title;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class TitleType extends Type
{
    public const string NAME = 'title';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] ??= 150;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Title) {
            throw InvalidType::new($value, self::NAME, ['null', Title::class]);
        }

        return $value->value();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Title
    {
        if (null === $value || $value instanceof Title) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, Title::class, ['null', 'string', Title::class]);
        }

        return Title::of($value);
    }
}
