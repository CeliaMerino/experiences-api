<?php

declare(strict_types=1);

namespace App\Session\Domain;

use App\Shared\Domain\Exception\InvalidValue;

final class Seats
{
    private function __construct(private int $value)
    {
    }

    public static function of(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidValue(sprintf('Seats must be at least 1, got %d.', $value));
        }

        return new self($value);
    }

    public static function none(): self
    {
        return new self(0);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function plus(self $other): self
    {
        return new self($this->value + $other->value);
    }

    public function minus(self $other): self
    {
        if ($other->value > $this->value) {
            throw new InvalidValue('Cannot release more seats than taken.');
        }

        return new self($this->value - $other->value);
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
