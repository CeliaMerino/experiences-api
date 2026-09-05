<?php

declare(strict_types=1);

namespace App\Booking\Domain;

interface BookingRepository
{
    public function save(Booking $booking): void;

    public function find(BookingId $id): ?Booking;
}
