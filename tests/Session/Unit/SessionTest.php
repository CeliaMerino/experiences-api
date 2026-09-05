<?php

declare(strict_types=1);

namespace App\Tests\Session\Unit;

use App\Experience\Domain\ExperienceId;
use App\Session\Domain\Capacity;
use App\Session\Domain\NotEnoughSeats;
use App\Session\Domain\Seats;
use App\Session\Domain\Session;
use App\Session\Domain\SessionAlreadyStarted;
use App\Session\Domain\SessionDayTaken;
use App\Session\Domain\SessionId;
use App\Session\Domain\SessionInThePast;
use App\Session\Domain\SessionNotFound;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Exception\Conflict;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\Exception\NotFound;
use App\Shared\Domain\ValueObject\Money;
use App\Tests\Shared\FrozenClock;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function testReservar3SobreAforo10Con8OcupadasLanzaNotEnoughSeatsYDejaElContadorEn8(): void
    {
        $session = $this->schedule(Capacity::of(10));
        $session->reserve(Seats::of(8), $this->clock());

        try {
            $session->reserve(Seats::of(3), $this->clock());
            self::fail('Expected NotEnoughSeats');
        } catch (NotEnoughSeats $error) {
            self::assertSame('not-enough-seats', $error->errorType());
            self::assertSame(8, $session->seatsTaken()->value());
        }
    }

    public function testProgramarConStartsAtAnteriorAlRelojLanzaSessionInThePastCapturableComoInvalidValue(): void
    {
        try {
            $this->schedule(
                startsAt: new \DateTimeImmutable('2026-06-15T11:00:00+00:00'),
            );
            self::fail('Expected SessionInThePast');
        } catch (SessionInThePast $error) {
            self::assertInstanceOf(InvalidValue::class, $error);
            self::assertSame('session-in-the-past', $error->errorType());
        }
    }

    public function testProgramarConStartsAtIgualAlRelojLanzaSessionInThePast(): void
    {
        $this->expectException(SessionInThePast::class);

        $this->schedule(startsAt: $this->clock()->now());
    }

    public function testConstructorEsPrivado(): void
    {
        $constructor = new \ReflectionClass(Session::class)->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
    }

    public function testAforoCeroONegativoLanzaInvalidValue(): void
    {
        try {
            Capacity::of(0);
            self::fail('Expected InvalidValue for capacity 0');
        } catch (InvalidValue) {
        }

        $this->expectException(InvalidValue::class);

        Capacity::of(-1);
    }

    public function testReservarEnSesionYaEmpezadaLanzaSessionAlreadyStarted(): void
    {
        $startsAt = new \DateTimeImmutable('2026-06-15T14:00:00+00:00');
        $session = $this->schedule(startsAt: $startsAt);

        $this->expectException(SessionAlreadyStarted::class);

        $session->reserve(Seats::of(1), FrozenClock::at('2026-06-15T14:00:00+00:00'));
    }

    public function testReservarPlazasDisponiblesIncrementaElContador(): void
    {
        $session = $this->schedule(Capacity::of(10));

        $session->reserve(Seats::of(3), $this->clock());

        self::assertSame(3, $session->seatsTaken()->value());
        self::assertSame(7, $session->seatsAvailable()->value());
    }

    public function testProgramarDejaElContadorACeroYPlazasLibresIgualesAlAforo(): void
    {
        $session = $this->schedule(Capacity::of(10));

        self::assertTrue(Seats::none()->equals($session->seatsTaken()));
        self::assertSame(10, $session->seatsAvailable()->value());
    }

    public function testProgramarExponeIdentidadAforoPrecioYContadores(): void
    {
        $id = SessionId::generate();
        $experienceId = ExperienceId::generate();
        $startsAt = new \DateTimeImmutable('2026-06-16T10:00:00+00:00');
        $capacity = Capacity::of(10);
        $price = Money::of(1250, 'EUR');

        $session = Session::schedule(
            $id,
            $experienceId,
            $startsAt,
            $capacity,
            $price,
            $this->clock(),
        );

        self::assertTrue($id->equals($session->id()));
        self::assertTrue($experienceId->equals($session->experienceId()));
        self::assertEquals($startsAt, $session->startsAt());
        self::assertTrue($capacity->equals($session->capacity()));
        self::assertTrue($price->equals($session->price()));
        self::assertTrue(Seats::none()->equals($session->seatsTaken()));
        self::assertSame(10, $session->seatsAvailable()->value());
    }

    public function testReleaseDevuelveLasPlazasAlContador(): void
    {
        $session = $this->schedule(Capacity::of(10));
        $session->reserve(Seats::of(4), $this->clock());

        $session->release(Seats::of(4));

        self::assertTrue(Seats::none()->equals($session->seatsTaken()));
        self::assertSame(10, $session->seatsAvailable()->value());
    }

    public function testReleaseNoDejaElContadorPorDebajoDeCero(): void
    {
        $session = $this->schedule();

        $this->expectException(InvalidValue::class);

        $session->release(Seats::of(1));
    }

    public function testPriceForMultiplicaElPrecioPorLasPlazas(): void
    {
        $session = $this->schedule(price: Money::of(1250, 'EUR'));

        self::assertTrue(Money::of(3750, 'EUR')->equals($session->priceFor(Seats::of(3))));
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

    public function testSessionNotFoundDevuelveSessionNotFoundEnErrorType(): void
    {
        $error = new SessionNotFound(SessionId::generate());

        self::assertSame('session-not-found', $error->errorType());
        self::assertInstanceOf(NotFound::class, $error);
    }

    public function testSessionDayTakenDevuelveSessionDayTakenEnErrorType(): void
    {
        $error = new SessionDayTaken(
            ExperienceId::generate(),
            new \DateTimeImmutable('2026-06-16T00:00:00+00:00'),
        );

        self::assertSame('session-day-taken', $error->errorType());
        self::assertInstanceOf(Conflict::class, $error);
    }

    public function testSessionAlreadyStartedDevuelveSessionAlreadyStartedEnErrorType(): void
    {
        $error = new SessionAlreadyStarted(SessionId::generate());

        self::assertSame('session-already-started', $error->errorType());
        self::assertInstanceOf(Conflict::class, $error);
    }

    public function testNotEnoughSeatsDevuelveNotEnoughSeatsEnErrorType(): void
    {
        $error = new NotEnoughSeats(SessionId::generate(), Seats::of(3), Seats::of(2));

        self::assertSame('not-enough-seats', $error->errorType());
        self::assertInstanceOf(Conflict::class, $error);
    }

    public function testSessionInThePastDevuelveSessionInThePastEnErrorType(): void
    {
        $error = new SessionInThePast(new \DateTimeImmutable('2026-06-15T11:00:00+00:00'));

        self::assertSame('session-in-the-past', $error->errorType());
        self::assertInstanceOf(InvalidValue::class, $error);
    }

    public function testHasStartedEsCiertoCuandoElRelojAlcanzaStartsAt(): void
    {
        $startsAt = new \DateTimeImmutable('2026-06-15T14:00:00+00:00');
        $session = $this->schedule(startsAt: $startsAt);

        self::assertFalse($session->hasStarted($this->clock()));
        self::assertTrue($session->hasStarted(FrozenClock::at('2026-06-15T14:00:00+00:00')));
    }

    public function testStartsWithinEsCiertoSiFaltanMenosDelIntervalo(): void
    {
        $session = $this->schedule(startsAt: new \DateTimeImmutable('2026-06-16T11:59:00+00:00'));
        $window = new \DateInterval('PT24H');

        self::assertTrue($session->startsWithin($window, $this->clock()));
        self::assertFalse(
            $this->schedule(startsAt: new \DateTimeImmutable('2026-06-16T12:00:01+00:00'))
                ->startsWithin($window, $this->clock()),
        );
    }

    private function clock(): FrozenClock
    {
        return FrozenClock::at('2026-06-15T12:00:00+00:00');
    }

    private function schedule(
        ?Capacity $capacity = null,
        ?\DateTimeImmutable $startsAt = null,
        ?Clock $clock = null,
        ?Money $price = null,
    ): Session {
        return Session::schedule(
            SessionId::generate(),
            ExperienceId::generate(),
            $startsAt ?? new \DateTimeImmutable('2026-06-16T10:00:00+00:00'),
            $capacity ?? Capacity::of(10),
            $price ?? Money::of(1250, 'EUR'),
            $clock ?? $this->clock(),
        );
    }
}
