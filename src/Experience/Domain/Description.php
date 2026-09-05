<?php

declare(strict_types=1);

namespace App\Experience\Domain;

final class Description
{
    private function __construct(private string $value)
    {
    }

    public static function of(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
