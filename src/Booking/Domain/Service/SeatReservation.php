<?php

declare(strict_types=1);

namespace App\Booking\Domain\Service;

use App\Booking\Domain\Booking;
use App\Booking\Domain\BookingId;
use App\Booking\Domain\ContactEmail;
use App\Booking\Domain\UserId;
use App\Session\Domain\Seats;
use App\Session\Domain\Session;
use App\Shared\Domain\Clock\Clock;

final class SeatReservation
{
    public function reserve(
        Session $session,
        UserId $userId,
        Seats $seats,
        ContactEmail $contactEmail,
        Clock $clock,
    ): Booking {
        $session->reserve($seats, $clock);

        return Booking::confirm(
            BookingId::generate(),
            $session->id(),
            $userId,
            $seats,
            $session->priceFor($seats),
            $contactEmail,
            $clock,
        );
    }

    public function cancel(Booking $booking, Session $session, Clock $clock): void
    {
        $booking->cancel($session->startsAt(), $clock);
        $session->release($booking->seats());
    }
}
