<?php

declare(strict_types=1);

namespace App\Booking\Application\Book;

use App\Booking\Domain\BookingId;
use App\Booking\Domain\BookingRepository;
use App\Booking\Domain\ContactEmail;
use App\Booking\Domain\Service\SeatReservation;
use App\Booking\Domain\UserId;
use App\Session\Domain\Seats;
use App\Session\Domain\SessionId;
use App\Session\Domain\SessionRepository;
use App\Shared\Domain\Clock\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class BookSeatsCommandHandler
{
    public function __construct(
        private SessionRepository $sessions,
        private BookingRepository $bookings,
        private SeatReservation $seatReservation,
        private Clock $clock,
    ) {
    }

    public function __invoke(BookSeatsCommand $command): BookingId
    {
        $session = $this->sessions->getForUpdate(SessionId::fromString($command->sessionId));

        $booking = $this->seatReservation->reserve(
            $session,
            UserId::fromString($command->userId),
            Seats::of($command->seats),
            ContactEmail::of($command->contactEmail),
            $this->clock,
        );

        $this->sessions->save($session);
        $this->bookings->save($booking);

        return $booking->id();
    }
}
