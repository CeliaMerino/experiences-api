<?php

declare(strict_types=1);

namespace App\Experience\Infrastructure\Persistence;

use App\Experience\Domain\ProviderId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class ProviderIdType extends Type
{
    public const string NAME = 'provider_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof ProviderId) {
            throw InvalidType::new($value, self::NAME, ['null', ProviderId::class]);
        }

        return $value->value();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ProviderId
    {
        if (null === $value || $value instanceof ProviderId) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, ProviderId::class, ['null', 'string', ProviderId::class]);
        }

        return ProviderId::fromString($value);
    }
}
