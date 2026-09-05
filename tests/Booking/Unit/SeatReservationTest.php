<?php

declare(strict_types=1);

namespace App\Tests\Booking\Unit;

use App\Booking\Domain\BookingAlreadyCancelled;
use App\Booking\Domain\BookingStatus;
use App\Booking\Domain\ContactEmail;
use App\Booking\Domain\Service\SeatReservation;
use App\Booking\Domain\UserId;
use App\Experience\Domain\ExperienceId;
use App\Session\Domain\Capacity;
use App\Session\Domain\NotEnoughSeats;
use App\Session\Domain\Seats;
use App\Session\Domain\Session;
use App\Session\Domain\SessionId;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Money;
use App\Tests\Shared\FrozenClock;
use PHPUnit\Framework\TestCase;

final class SeatReservationTest extends TestCase
{
    public function testReservar3Sobre10LibresDejaSeatsTakenA3YReservaConfirmada(): void
    {
        $session = $this->schedule(Capacity::of(10));
        $service = new SeatReservation();

        $booking = $service->reserve(
            $session,
            UserId::generate(),
            Seats::of(3),
            ContactEmail::of('cliente@example.com'),
            $this->clock(),
        );

        self::assertSame(3, $session->seatsTaken()->value());
        self::assertSame(BookingStatus::Confirmed, $booking->status());
        self::assertTrue($session->id()->equals($booking->sessionId()));
        self::assertTrue(Money::of(3750, 'EUR')->equals($booking->total()));
    }

    public function testReservar3Sobre2LibresLanzaNotEnoughSeatsYDejaLaSesionIntacta(): void
    {
        $session = $this->schedule(Capacity::of(2));
        $service = new SeatReservation();

        try {
            $service->reserve(
                $session,
                UserId::generate(),
                Seats::of(3),
                ContactEmail::of('cliente@example.com'),
                $this->clock(),
            );
            self::fail('Expected NotEnoughSeats');
        } catch (NotEnoughSeats $error) {
            self::assertSame('not-enough-seats', $error->errorType());
            self::assertSame(0, $session->seatsTaken()->value());
        }
    }

    public function testCancelarDevuelveLasPlazasExactasAlContador(): void
    {
        $session = $this->schedule(Capacity::of(10));
        $service = new SeatReservation();
        $booking = $service->reserve(
            $session,
            UserId::generate(),
            Seats::of(3),
            ContactEmail::of('cliente@example.com'),
            $this->clock(),
        );

        $service->cancel($booking, $session, $this->clock());

        self::assertSame(BookingStatus::Cancelled, $booking->status());
        self::assertSame(0, $session->seatsTaken()->value());
    }

    public function testCancelarUnaReservaYaCanceladaNoAlteraElContador(): void
    {
        $session = $this->schedule(Capacity::of(10));
        $service = new SeatReservation();
        $booking = $service->reserve(
            $session,
            UserId::generate(),
            Seats::of(3),
            ContactEmail::of('cliente@example.com'),
            $this->clock(),
        );
        $service->cancel($booking, $session, $this->clock());
        self::assertSame(0, $session->seatsTaken()->value());

        try {
            $service->cancel($booking, $session, $this->clock());
            self::fail('Expected BookingAlreadyCancelled');
        } catch (BookingAlreadyCancelled) {
            self::assertSame(0, $session->seatsTaken()->value());
            self::assertSame(BookingStatus::Cancelled, $booking->status());
        }
    }

    private function clock(): FrozenClock
    {
        return FrozenClock::at('2026-06-15T12:00:00+00:00');
    }

    private function schedule(
        Capacity $capacity,
        ?\DateTimeImmutable $startsAt = null,
        ?Clock $clock = null,
    ): Session {
        return Session::schedule(
            SessionId::generate(),
            ExperienceId::generate(),
            $startsAt ?? new \DateTimeImmutable('2026-06-20T12:00:00+00:00'),
            $capacity,
            Money::of(1250, 'EUR'),
            $clock ?? $this->clock(),
        );
    }
}
