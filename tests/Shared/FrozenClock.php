<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Domain\Clock\Clock;

final class FrozenClock implements Clock
{
    public function __construct(private readonly \DateTimeImmutable $now)
    {
    }

    public static function at(string $atom): self
    {
        return new self(new \DateTimeImmutable($atom));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
