<?php

declare(strict_types=1);

namespace App\Session\Domain;

use App\Experience\Domain\ExperienceId;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Money;

final class Session
{
    private function __construct(
        private SessionId $id,
        private ExperienceId $experienceId,
        private \DateTimeImmutable $startsAt,
        private Capacity $capacity,
        private Seats $seatsTaken,
        private Money $price,
    ) {
    }

    public static function schedule(
        SessionId $id,
        ExperienceId $experienceId,
        \DateTimeImmutable $startsAt,
        Capacity $capacity,
        Money $price,
        Clock $clock,
    ): self {
        if ($startsAt <= $clock->now()) {
            throw new SessionInThePast($startsAt);
        }

        return new self($id, $experienceId, $startsAt, $capacity, Seats::none(), $price);
    }

    public function reserve(Seats $seats, Clock $clock): void
    {
        if ($this->hasStarted($clock)) {
            throw new SessionAlreadyStarted($this->id);
        }

        $available = $this->seatsAvailable();

        if ($seats->isGreaterThan($available)) {
            throw new NotEnoughSeats($this->id, $seats, $available);
        }

        $this->seatsTaken = $this->seatsTaken->plus($seats);
    }

    public function release(Seats $seats): void
    {
        $this->seatsTaken = $this->seatsTaken->minus($seats);
    }

    public function seatsAvailable(): Seats
    {
        $available = $this->capacity->value() - $this->seatsTaken->value();

        if (0 === $available) {
            return Seats::none();
        }

        return Seats::of($available);
    }

    public function hasStarted(Clock $clock): bool
    {
        return $clock->now() >= $this->startsAt;
    }

    public function startsWithin(\DateInterval $window, Clock $clock): bool
    {
        return $this->startsAt <= $clock->now()->add($window);
    }

    public function priceFor(Seats $seats): Money
    {
        return $this->price->multiply($seats->value());
    }

    public function id(): SessionId
    {
        return $this->id;
    }

    public function experienceId(): ExperienceId
    {
        return $this->experienceId;
    }

    public function startsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function capacity(): Capacity
    {
        return $this->capacity;
    }

    public function seatsTaken(): Seats
    {
        return $this->seatsTaken;
    }

    public function price(): Money
    {
        return $this->price;
    }
}
