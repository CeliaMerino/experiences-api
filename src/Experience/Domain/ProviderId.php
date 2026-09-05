<?php

declare(strict_types=1);

namespace App\Experience\Domain;

use App\Shared\Domain\ValueObject\Uuid;

final class ProviderId
{
    private function __construct(private Uuid $value)
    {
    }

    public static function generate(): self
    {
        return new self(Uuid::generate());
    }

    public static function fromString(string $value): self
    {
        return new self(Uuid::fromString($value));
    }

    public function value(): string
    {
        return $this->value->value();
    }

    public function equals(self $other): bool
    {
        return $this->value->equals($other->value);
    }
}
