<?php

declare(strict_types=1);

namespace App\Experience\Infrastructure\Persistence;

use App\Experience\Domain\ExperienceId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class ExperienceIdType extends Type
{
    public const string NAME = 'experience_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof ExperienceId) {
            throw InvalidType::new($value, self::NAME, ['null', ExperienceId::class]);
        }

        return $value->value();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ExperienceId
    {
        if (null === $value || $value instanceof ExperienceId) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, ExperienceId::class, ['null', 'string', ExperienceId::class]);
        }

        return ExperienceId::fromString($value);
    }
}
