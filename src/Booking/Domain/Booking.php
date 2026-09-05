<?php

declare(strict_types=1);

namespace App\Booking\Domain;

use App\Session\Domain\Seats;
use App\Session\Domain\SessionId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Money;

final class Booking extends AggregateRoot
{
    private const string CANCELLATION_WINDOW = 'PT24H';

    private function __construct(
        private BookingId $id,
        private SessionId $sessionId,
        private UserId $userId,
        private Seats $seats,
        private Money $total,
        private BookingStatus $status,
        private ContactEmail $contactEmail,
    ) {
    }

    public static function confirm(
        BookingId $id,
        SessionId $sessionId,
        UserId $userId,
        Seats $seats,
        Money $total,
        ContactEmail $contactEmail,
        Clock $clock,
    ): self {
        $booking = new self(
            $id,
            $sessionId,
            $userId,
            $seats,
            $total,
            BookingStatus::Confirmed,
            $contactEmail,
        );

        $booking->record(new BookingWasConfirmed(
            $id->value(),
            $contactEmail->value(),
            $clock->now(),
        ));

        return $booking;
    }

    public function cancel(\DateTimeImmutable $sessionStartsAt, Clock $clock): void
    {
        if (BookingStatus::Cancelled === $this->status) {
            throw new BookingAlreadyCancelled($this->id);
        }

        $windowClosesAt = $clock->now()->add(new \DateInterval(self::CANCELLATION_WINDOW));

        if ($sessionStartsAt <= $windowClosesAt) {
            throw new CancellationWindowClosed($this->id);
        }

        $this->status = BookingStatus::Cancelled;

        $this->record(new BookingWasCancelled(
            $this->id->value(),
            $this->contactEmail->value(),
            $clock->now(),
        ));
    }

    public function id(): BookingId
    {
        return $this->id;
    }

    public function sessionId(): SessionId
    {
        return $this->sessionId;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function seats(): Seats
    {
        return $this->seats;
    }

    public function total(): Money
    {
        return $this->total;
    }

    public function status(): BookingStatus
    {
        return $this->status;
    }

    public function contactEmail(): ContactEmail
    {
        return $this->contactEmail;
    }
}
