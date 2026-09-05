<?php

declare(strict_types=1);

namespace App\Session\Domain;

use App\Shared\Domain\Exception\InvalidValue;

final class Capacity
{
    private function __construct(private int $value)
    {
    }

    public static function of(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidValue(sprintf('Capacity must be greater than zero, got %d.', $value));
        }

        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
