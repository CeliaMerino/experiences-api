<?php

declare(strict_types=1);

namespace App\Booking\Application\Cancel;

use App\Booking\Domain\BookingId;
use App\Booking\Domain\BookingNotFound;
use App\Booking\Domain\BookingRepository;
use App\Booking\Domain\Service\SeatReservation;
use App\Session\Domain\SessionRepository;
use App\Shared\Domain\Clock\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CancelBookingCommandHandler
{
    public function __construct(
        private BookingRepository $bookings,
        private SessionRepository $sessions,
        private SeatReservation $seatReservation,
        private Clock $clock,
    ) {
    }

    public function __invoke(CancelBookingCommand $command): void
    {
        $id = BookingId::fromString($command->bookingId);
        $booking = $this->bookings->find($id) ?? throw new BookingNotFound($id);

        $session = $this->sessions->getForUpdate($booking->sessionId());

        $this->seatReservation->cancel($booking, $session, $this->clock);

        $this->bookings->save($booking);
        $this->sessions->save($session);
    }
}
