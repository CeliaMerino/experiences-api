<?php

declare(strict_types=1);

namespace App\Booking\Domain;

use App\Shared\Domain\Exception\NotFound;

final class BookingNotFound extends NotFound
{
    public function __construct(BookingId $id)
    {
        parent::__construct(sprintf('Booking "%s" not found.', $id->value()));
    }

    public function errorType(): string
    {
        return 'booking-not-found';
    }
}
