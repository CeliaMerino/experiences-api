<?php

declare(strict_types=1);

namespace App\Session\Domain;

use App\Experience\Domain\ExperienceId;

interface SessionRepository
{
    public function save(Session $session): void;

    public function find(SessionId $id): ?Session;

    public function getForUpdate(SessionId $id): Session;

    public function hasSessionOnDay(ExperienceId $experienceId, \DateTimeImmutable $day): bool;
}
