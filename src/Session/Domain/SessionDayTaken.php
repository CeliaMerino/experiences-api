<?php

declare(strict_types=1);

namespace App\Session\Domain;

use App\Experience\Domain\ExperienceId;
use App\Shared\Domain\Exception\Conflict;

final class SessionDayTaken extends Conflict
{
    public function __construct(ExperienceId $experienceId, \DateTimeImmutable $day)
    {
        parent::__construct(sprintf(
            'Experience "%s" already has a session on %s.',
            $experienceId->value(),
            $day->format('Y-m-d'),
        ));
    }

    public function errorType(): string
    {
        return 'session-day-taken';
    }
}
