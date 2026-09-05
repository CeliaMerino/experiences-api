<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidValue;

final class Money
{
    private function __construct(
        private readonly int $amount,
        private readonly string $currency,
    ) {
    }

    public static function of(int $amount, string $currency): self
    {
        if ($amount < 0) {
            throw new InvalidValue('Money amount cannot be negative.');
        }

        if (1 !== preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidValue(sprintf('Invalid currency: "%s".', $currency));
        }

        return new self($amount, $currency);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function multiply(int $factor): self
    {
        if ($factor < 0) {
            throw new InvalidValue('Money multiply factor cannot be negative.');
        }

        return new self($this->amount * $factor, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }
}
