<?php

declare(strict_types=1);

namespace App\Experience\Domain;

use App\Shared\Domain\Exception\InvalidValue;

final class Title
{
    private const int MAX_LENGTH = 150;

    private function __construct(private string $value)
    {
    }

    public static function of(string $value): self
    {
        $value = trim($value);

        if ('' === $value) {
            throw new InvalidValue('Title cannot be empty.');
        }

        if (strlen($value) > self::MAX_LENGTH) {
            throw new InvalidValue(sprintf('Title cannot exceed %d characters.', self::MAX_LENGTH));
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
