<?php

declare(strict_types=1);

namespace App\Booking\Domain;

use App\Shared\Domain\Exception\Conflict;

final class BookingAlreadyCancelled extends Conflict
{
    public function __construct(BookingId $id)
    {
        parent::__construct(sprintf('Booking "%s" is already cancelled.', $id->value()));
    }

    public function errorType(): string
    {
        return 'booking-already-cancelled';
    }
}
