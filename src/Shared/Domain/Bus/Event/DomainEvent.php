<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Event;

interface DomainEvent
{
    public function aggregateId(): string;

    public function occurredOn(): \DateTimeImmutable;
}
