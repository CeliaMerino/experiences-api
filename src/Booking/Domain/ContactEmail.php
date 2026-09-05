<?php

declare(strict_types=1);

namespace App\Booking\Domain;

use App\Shared\Domain\Exception\InvalidValue;

final class ContactEmail
{
    private function __construct(private string $value)
    {
    }

    public static function of(string $value): self
    {
        $value = trim($value);

        if (false === filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw new InvalidValue(sprintf('Invalid contact email: "%s".', $value));
        }

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
