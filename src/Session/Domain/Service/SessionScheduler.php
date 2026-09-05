<?php

declare(strict_types=1);

namespace App\Session\Domain\Service;

use App\Experience\Domain\Experience;
use App\Session\Domain\Capacity;
use App\Session\Domain\Session;
use App\Session\Domain\SessionDayTaken;
use App\Session\Domain\SessionId;
use App\Session\Domain\SessionRepository;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Money;

final class SessionScheduler
{
    public function __construct(
        private SessionRepository $sessions,
        private Clock $clock,
    ) {
    }

    public function schedule(
        Experience $experience,
        \DateTimeImmutable $startsAt,
        Capacity $capacity,
        Money $price,
    ): Session {
        $startsAt = $startsAt->setTimezone($experience->timezone());
        $day = $startsAt->setTime(0, 0);

        if ($this->sessions->hasSessionOnDay($experience->id(), $day)) {
            throw new SessionDayTaken($experience->id(), $day);
        }

        return Session::schedule(
            SessionId::generate(),
            $experience->id(),
            $startsAt,
            $capacity,
            $price,
            $this->clock,
        );
    }
}
