<?php

declare(strict_types=1);

namespace App\Tests\Booking\Unit;

use App\Booking\Domain\Booking;
use App\Booking\Domain\BookingAlreadyCancelled;
use App\Booking\Domain\BookingId;
use App\Booking\Domain\BookingNotFound;
use App\Booking\Domain\BookingStatus;
use App\Booking\Domain\BookingWasCancelled;
use App\Booking\Domain\BookingWasConfirmed;
use App\Booking\Domain\CancellationWindowClosed;
use App\Booking\Domain\ContactEmail;
use App\Booking\Domain\UserId;
use App\Session\Domain\Seats;
use App\Session\Domain\SessionId;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Exception\Conflict;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\Exception\NotFound;
use App\Shared\Domain\ValueObject\Money;
use App\Tests\Shared\FrozenClock;
use PHPUnit\Framework\TestCase;

final class BookingTest extends TestCase
{
    public function testConfirmCon4PlazasA1250DejaTotalEn5000YRegistraBookingWasConfirmed(): void
    {
        $clock = $this->clock();
        $email = ContactEmail::of('cliente@example.com');
        $total = Money::of(1250, 'EUR')->multiply(4);

        $booking = Booking::confirm(
            BookingId::generate(),
            SessionId::generate(),
            UserId::generate(),
            Seats::of(4),
            $total,
            $email,
            $clock,
        );

        self::assertTrue(Money::of(5000, 'EUR')->equals($booking->total()));
        self::assertSame(BookingStatus::Confirmed, $booking->status());

        $events = $booking->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BookingWasConfirmed::class, $events[0]);
        self::assertSame($booking->id()->value(), $events[0]->aggregateId());
        self::assertSame('cliente@example.com', $events[0]->contactEmail());
        self::assertEquals($clock->now(), $events[0]->occurredOn());
    }

    public function testUnaSegundaCancelacionLanzaBookingAlreadyCancelledYNoRegistraOtroEvento(): void
    {
        $booking = $this->confirmedBooking();
        $booking->pullDomainEvents();

        $sessionStartsAt = new \DateTimeImmutable('2026-06-20T12:00:00+00:00');
        $booking->cancel($sessionStartsAt, $this->clock());

        $eventsAfterFirst = $booking->pullDomainEvents();
        self::assertCount(1, $eventsAfterFirst);
        self::assertInstanceOf(BookingWasCancelled::class, $eventsAfterFirst[0]);

        try {
            $booking->cancel($sessionStartsAt, $this->clock());
            self::fail('Expected BookingAlreadyCancelled');
        } catch (BookingAlreadyCancelled $error) {
            self::assertSame('booking-already-cancelled', $error->errorType());
            self::assertSame(BookingStatus::Cancelled, $booking->status());
            self::assertSame([], $booking->pullDomainEvents());
        }
    }

    public function testCancelarA23h59DelInicioLanzaCancellationWindowClosed(): void
    {
        $booking = $this->confirmedBooking();
        $booking->pullDomainEvents();

        $sessionStartsAt = new \DateTimeImmutable('2026-06-16T11:59:00+00:00');

        try {
            $booking->cancel($sessionStartsAt, $this->clock());
            self::fail('Expected CancellationWindowClosed');
        } catch (CancellationWindowClosed $error) {
            self::assertSame('cancellation-window-closed', $error->errorType());
            self::assertSame(BookingStatus::Confirmed, $booking->status());
            self::assertSame([], $booking->pullDomainEvents());
        }
    }

    public function testSeatsCeroONegativoLanzaInvalidValue(): void
    {
        try {
            Seats::of(0);
            self::fail('Expected InvalidValue for 0 seats');
        } catch (InvalidValue) {
        }

        $this->expectException(InvalidValue::class);

        Seats::of(-1);
    }

    public function testCancelarConMasDe24HorasDejaCancelledYRegistraBookingWasCancelled(): void
    {
        $booking = $this->confirmedBooking();
        $booking->pullDomainEvents();
        $clock = $this->clock();

        $booking->cancel(new \DateTimeImmutable('2026-06-20T12:00:00+00:00'), $clock);

        self::assertSame(BookingStatus::Cancelled, $booking->status());

        $events = $booking->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BookingWasCancelled::class, $events[0]);
        self::assertSame($booking->id()->value(), $events[0]->aggregateId());
        self::assertSame($booking->contactEmail()->value(), $events[0]->contactEmail());
        self::assertEquals($clock->now(), $events[0]->occurredOn());
    }

    public function testCancelarExactamenteA24HorasLanzaCancellationWindowClosed(): void
    {
        $booking = $this->confirmedBooking();

        $this->expectException(CancellationWindowClosed::class);

        $booking->cancel(new \DateTimeImmutable('2026-06-16T12:00:00+00:00'), $this->clock());
    }

    public function testCorreoDeContactoInvalidoLanzaInvalidValue(): void
    {
        $this->expectException(InvalidValue::class);

        ContactEmail::of('no-es-un-correo');
    }

    public function testConstructorEsPrivado(): void
    {
        $constructor = new \ReflectionClass(Booking::class)->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
    }

    public function testBookingNotFoundDevuelveBookingNotFoundEnErrorType(): void
    {
        $error = new BookingNotFound(BookingId::generate());

        self::assertSame('booking-not-found', $error->errorType());
        self::assertInstanceOf(NotFound::class, $error);
    }

    public function testBookingAlreadyCancelledDevuelveBookingAlreadyCancelledEnErrorType(): void
    {
        $error = new BookingAlreadyCancelled(BookingId::generate());

        self::assertSame('booking-already-cancelled', $error->errorType());
        self::assertInstanceOf(Conflict::class, $error);
    }

    public function testCancellationWindowClosedDevuelveCancellationWindowClosedEnErrorType(): void
    {
        $error = new CancellationWindowClosed(BookingId::generate());

        self::assertSame('cancellation-window-closed', $error->errorType());
        self::assertInstanceOf(Conflict::class, $error);
    }

    private function clock(): FrozenClock
    {
        return FrozenClock::at('2026-06-15T12:00:00+00:00');
    }

    private function confirmedBooking(?Clock $clock = null): Booking
    {
        return Booking::confirm(
            BookingId::generate(),
            SessionId::generate(),
            UserId::generate(),
            Seats::of(2),
            Money::of(2500, 'EUR'),
            ContactEmail::of('cliente@example.com'),
            $clock ?? $this->clock(),
        );
    }
}
