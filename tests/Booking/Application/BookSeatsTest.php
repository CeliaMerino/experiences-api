<?php

declare(strict_types=1);

namespace App\Tests\Booking\Application;

use App\Booking\Application\Book\BookSeatsCommand;
use App\Booking\Application\Book\BookSeatsCommandHandler;
use App\Booking\Domain\BookingStatus;
use App\Booking\Domain\Service\SeatReservation;
use App\Booking\Domain\UserId;
use App\Experience\Domain\ExperienceId;
use App\Session\Domain\Capacity;
use App\Session\Domain\NotEnoughSeats;
use App\Session\Domain\Session;
use App\Session\Domain\SessionAlreadyStarted;
use App\Session\Domain\SessionId;
use App\Session\Domain\SessionNotFound;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\Money;
use App\Tests\Booking\InMemoryBookingRepository;
use App\Tests\Session\InMemorySessionRepository;
use App\Tests\Shared\FrozenClock;
use PHPUnit\Framework\TestCase;

final class BookSeatsTest extends TestCase
{
    public function testReservar3Sobre10LibresPersisteSeatsTakenA3YReservaConfirmada(): void
    {
        $sessions = new InMemorySessionRepository();
        $bookings = new InMemoryBookingRepository();
        $session = $this->persistSession($sessions, Capacity::of(10));
        $handler = $this->handler($sessions, $bookings);

        $id = $handler(new BookSeatsCommand(
            $session->id()->value(),
            UserId::generate()->value(),
            3,
            'cliente@example.com',
        ));

        $storedSession = $sessions->find($session->id());
        $storedBooking = $bookings->find($id);

        self::assertNotNull($storedSession);
        self::assertNotNull($storedBooking);
        self::assertSame(3, $storedSession->seatsTaken()->value());
        self::assertSame(BookingStatus::Confirmed, $storedBooking->status());
        self::assertTrue(Money::of(3750, 'EUR')->equals($storedBooking->total()));
    }

    public function testReservar3Sobre2LibresLanzaNotEnoughSeatsYDejaLaSesionIntacta(): void
    {
        $sessions = new InMemorySessionRepository();
        $bookings = new InMemoryBookingRepository();
        $session = $this->persistSession($sessions, Capacity::of(2));
        $handler = $this->handler($sessions, $bookings);

        try {
            $handler(new BookSeatsCommand(
                $session->id()->value(),
                UserId::generate()->value(),
                3,
                'cliente@example.com',
            ));
            self::fail('Expected NotEnoughSeats');
        } catch (NotEnoughSeats $error) {
            self::assertSame('not-enough-seats', $error->errorType());
            $stored = $sessions->find($session->id());
            self::assertNotNull($stored);
            self::assertSame(0, $stored->seatsTaken()->value());
        }
    }

    public function testPlazasCeroLanzaInvalidValue(): void
    {
        $sessions = new InMemorySessionRepository();
        $session = $this->persistSession($sessions, Capacity::of(10));
        $handler = $this->handler($sessions, new InMemoryBookingRepository());

        $this->expectException(InvalidValue::class);

        $handler(new BookSeatsCommand(
            $session->id()->value(),
            UserId::generate()->value(),
            0,
            'cliente@example.com',
        ));
    }

    public function testCorreoInvalidoLanzaInvalidValue(): void
    {
        $sessions = new InMemorySessionRepository();
        $session = $this->persistSession($sessions, Capacity::of(10));
        $handler = $this->handler($sessions, new InMemoryBookingRepository());

        $this->expectException(InvalidValue::class);

        $handler(new BookSeatsCommand(
            $session->id()->value(),
            UserId::generate()->value(),
            2,
            'no-es-un-email',
        ));
    }

    public function testSesionInexistenteLanzaSessionNotFound(): void
    {
        $handler = $this->handler(new InMemorySessionRepository(), new InMemoryBookingRepository());

        $this->expectException(SessionNotFound::class);

        $handler(new BookSeatsCommand(
            SessionId::generate()->value(),
            UserId::generate()->value(),
            1,
            'cliente@example.com',
        ));
    }

    public function testSesionYaEmpezadaLanzaSessionAlreadyStarted(): void
    {
        $sessions = new InMemorySessionRepository();
        $startsAt = new \DateTimeImmutable('2026-06-15T14:00:00+00:00');
        $session = $this->persistSession(
            $sessions,
            Capacity::of(10),
            $startsAt,
            FrozenClock::at('2026-06-15T12:00:00+00:00'),
        );
        $handler = $this->handler(
            $sessions,
            new InMemoryBookingRepository(),
            FrozenClock::at('2026-06-15T14:00:00+00:00'),
        );

        $this->expectException(SessionAlreadyStarted::class);

        $handler(new BookSeatsCommand(
            $session->id()->value(),
            UserId::generate()->value(),
            1,
            'cliente@example.com',
        ));
    }

    public function testElManejadorNoContieneCondicionalesNiAritmetica(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3).'/src/Booking/Application/Book/BookSeatsCommandHandler.php',
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

    private function handler(
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
