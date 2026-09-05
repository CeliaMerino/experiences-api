<?php

declare(strict_types=1);

namespace App\Session\Infrastructure\Persistence;

use App\Session\Domain\SessionId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class SessionIdType extends Type
{
    public const string NAME = 'session_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof SessionId) {
            throw InvalidType::new($value, self::NAME, ['null', SessionId::class]);
        }

        return $value->value();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?SessionId
    {
        if (null === $value || $value instanceof SessionId) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, SessionId::class, ['null', 'string', SessionId::class]);
        }

        return SessionId::fromString($value);
    }
}
