<?php

declare(strict_types=1);

namespace App\Booking\Infrastructure\Notification;

use App\Booking\Domain\BookingId;
use App\Booking\Domain\BookingRepository;
use App\Booking\Domain\BookingWasCancelled;
use App\Shared\Domain\Mailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'event.bus')]
final class SendBookingCancellationEmail
{
    public function __construct(
        private Mailer $mailer,
        private BookingRepository $bookings,
    ) {
    }

    public function __invoke(BookingWasCancelled $event): void
    {
        $booking = $this->bookings->find(BookingId::fromString($event->aggregateId()));
        if (null === $booking) {
            throw new \LogicException(sprintf('Booking "%s" not found for cancellation email.', $event->aggregateId()));
        }

        $this->mailer->send(
            $event->contactEmail(),
            'Reserva cancelada',
            sprintf(
                "Reserva %s cancelada.\nPlazas: %d\nImporte: %d %s\nLa v1 no contempla reembolso.",
                $booking->id()->value(),
                $booking->seats()->value(),
                $booking->total()->amount(),
                $booking->total()->currency(),
            ),
        );
    }
}
