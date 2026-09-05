<?php

declare(strict_types=1);

namespace App\Booking\Domain;

use App\Shared\Domain\Bus\Event\DomainEvent;

final readonly class BookingWasConfirmed implements DomainEvent
{
    public function __construct(
        private string $aggregateId,
        private string $contactEmail,
        private \DateTimeImmutable $occurredOn,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    public function contactEmail(): string
    {
        return $this->contactEmail;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
