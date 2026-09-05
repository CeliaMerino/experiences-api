<?php

declare(strict_types=1);

namespace App\Tests\Booking;

use App\Booking\Domain\Booking;
use App\Booking\Domain\BookingId;
use App\Booking\Domain\BookingRepository;

final class InMemoryBookingRepository implements BookingRepository
{
    /** @var array<string, Booking> */
    private array $items = [];

    public function save(Booking $booking): void
    {
        $this->items[$booking->id()->value()] = $booking;
    }

    public function find(BookingId $id): ?Booking
    {
        return $this->items[$id->value()] ?? null;
    }
}
