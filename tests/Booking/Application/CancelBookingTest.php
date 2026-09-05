<?php

declare(strict_types=1);

namespace App\Tests\Booking\Application;

use App\Booking\Application\Book\BookSeatsCommand;
use App\Booking\Application\Book\BookSeatsCommandHandler;
use App\Booking\Application\Cancel\CancelBookingCommand;
use App\Booking\Application\Cancel\CancelBookingCommandHandler;
use App\Booking\Domain\BookingAlreadyCancelled;
use App\Booking\Domain\BookingId;
use App\Booking\Domain\BookingNotFound;
use App\Booking\Domain\BookingStatus;
use App\Booking\Domain\CancellationWindowClosed;
use App\Booking\Domain\Service\SeatReservation;
use App\Booking\Domain\UserId;
use App\Experience\Domain\ExperienceId;
use App\Session\Domain\Capacity;
use App\Session\Domain\Session;
use App\Session\Domain\SessionId;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Money;
use App\Tests\Booking\InMemoryBookingRepository;
use App\Tests\Session\InMemorySessionRepository;
use App\Tests\Shared\FrozenClock;
use PHPUnit\Framework\TestCase;

final class CancelBookingTest extends TestCase
{
    public function testCancelarDevuelveLasPlazasExactasAlContador(): void
    {
        [$sessions, $bookings, $sessionId, $bookingId] = $this->booked(3);

        $this->cancelHandler($sessions, $bookings)(new CancelBookingCommand($bookingId->value()));

        $session = $sessions->find($sessionId);
        $booking = $bookings->find($bookingId);

        self::assertNotNull($session);
        self::assertNotNull($booking);
        self::assertSame(0, $session->seatsTaken()->value());
        self::assertSame(BookingStatus::Cancelled, $booking->status());
    }

    public function testCancelarUnaReservaYaCanceladaNoAlteraElContador(): void
    {
        [$sessions, $bookings, $sessionId, $bookingId] = $this->booked(3);
        $cancel = $this->cancelHandler($sessions, $bookings);

        $cancel(new CancelBookingCommand($bookingId->value()));
        $sessionAfterFirst = $sessions->find($sessionId);
        self::assertNotNull($sessionAfterFirst);
        self::assertSame(0, $sessionAfterFirst->seatsTaken()->value());

        try {
            $cancel(new CancelBookingCommand($bookingId->value()));
            self::fail('Expected BookingAlreadyCancelled');
        } catch (BookingAlreadyCancelled) {
            $sessionAfterSecond = $sessions->find($sessionId);
            $bookingAfterSecond = $bookings->find($bookingId);
            self::assertNotNull($sessionAfterSecond);
            self::assertNotNull($bookingAfterSecond);
            self::assertSame(0, $sessionAfterSecond->seatsTaken()->value());
            self::assertSame(BookingStatus::Cancelled, $bookingAfterSecond->status());
        }
    }

    public function testReservaInexistenteLanzaBookingNotFound(): void
    {
        $handler = $this->cancelHandler(new InMemorySessionRepository(), new InMemoryBookingRepository());

        $this->expectException(BookingNotFound::class);

        $handler(new CancelBookingCommand(BookingId::generate()->value()));
    }

    public function testVentanaDeCancelacionCerradaLanzaCancellationWindowClosed(): void
    {
        $sessions = new InMemorySessionRepository();
        $bookings = new InMemoryBookingRepository();
        $startsAt = new \DateTimeImmutable('2026-06-16T11:59:00+00:00');
        $session = $this->persistSession($sessions, Capacity::of(10), $startsAt);
        $bookingId = $this->bookHandler($sessions, $bookings)(new BookSeatsCommand(
            $session->id()->value(),
            UserId::generate()->value(),
            2,
            'cliente@example.com',
        ));

        $this->expectException(CancellationWindowClosed::class);

        $this->cancelHandler($sessions, $bookings)(new CancelBookingCommand($bookingId->value()));
    }

    public function testElManejadorNoContieneCondicionalesNiAritmetica(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3).'/src/Booking/Application/Cancel/CancelBookingCommandHandler.php',
        );
        self::assertNotFalse($source);

        foreach (token_get_all($source) as $token) {
            if (!\is_array($token)) {
                self::assertNotContains(
                    $token,
                    ['>', '<', '+', '-', '*', '/'],
                    sprintf('Operador prohibido "%s" en el manejador.', $token),
                );
                continue;
            }

            self::assertNotSame(
                \T_IF,
                $token[0],
                'El manejador no puede contener if.',
            );
            self::assertNotContains(
                $token[0],
                [\T_IS_GREATER_OR_EQUAL, \T_IS_SMALLER_OR_EQUAL, \T_SPACESHIP],
                sprintf('Operador de comparación prohibido en el manejador: %s', $token[1]),
            );
        }
    }

    /**
     * @return array{InMemorySessionRepository, InMemoryBookingRepository, SessionId, BookingId}
     */
    private function booked(int $seats): array
    {
        $sessions = new InMemorySessionRepository();
        $bookings = new InMemoryBookingRepository();
        $session = $this->persistSession($sessions, Capacity::of(10));
        $bookingId = $this->bookHandler($sessions, $bookings)(new BookSeatsCommand(
            $session->id()->value(),
            UserId::generate()->value(),
            $seats,
            'cliente@example.com',
        ));

        return [$sessions, $bookings, $session->id(), $bookingId];
    }

    private function bookHandler(
        InMemorySessionRepository $sessions,
        InMemoryBookingRepository $bookings,
        ?Clock $clock = null,
    ): BookSeatsCommandHandler {
        return new BookSeatsCommandHandler(
            $sessions,
            $bookings,
            new SeatReservation(),
            $clock ?? FrozenClock::at('2026-06-15T12:00:00+00:00'),
        );
    }

    private function cancelHandler(
        InMemorySessionRepository $sessions,
        InMemoryBookingRepository $bookings,
        ?Clock $clock = null,
    ): CancelBookingCommandHandler {
        return new CancelBookingCommandHandler(
            $bookings,
            $sessions,
            new SeatReservation(),
            $clock ?? FrozenClock::at('2026-06-15T12:00:00+00:00'),
        );
    }

    private function persistSession(
        InMemorySessionRepository $sessions,
        Capacity $capacity,
        ?\DateTimeImmutable $startsAt = null,
        ?Clock $clock = null,
    ): Session {
        $session = Session::schedule(
            SessionId::generate(),
            ExperienceId::generate(),
            $startsAt ?? new \DateTimeImmutable('2026-06-20T12:00:00+00:00'),
            $capacity,
            Money::of(1250, 'EUR'),
            $clock ?? FrozenClock::at('2026-06-15T12:00:00+00:00'),
        );
        $sessions->save($session);

        return $session;
    }
}
