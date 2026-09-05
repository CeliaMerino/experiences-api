<?php

declare(strict_types=1);

namespace App\Tests\Session;

use App\Experience\Domain\ExperienceId;
use App\Session\Domain\Session;
use App\Session\Domain\SessionId;
use App\Session\Domain\SessionNotFound;
use App\Session\Domain\SessionRepository;

final class InMemorySessionRepository implements SessionRepository
{
    /** @var array<string, Session> */
    private array $items = [];

    public function save(Session $session): void
    {
        $this->items[$session->id()->value()] = $session;
    }

    public function find(SessionId $id): ?Session
    {
        return $this->items[$id->value()] ?? null;
    }

    public function getForUpdate(SessionId $id): Session
    {
        return $this->find($id) ?? throw new SessionNotFound($id);
    }

    public function hasSessionOnDay(ExperienceId $experienceId, \DateTimeImmutable $day): bool
    {
        $dayKey = $day->format('Y-m-d');

        foreach ($this->items as $session) {
            if (
                $session->experienceId()->equals($experienceId)
                && $session->startsAt()->format('Y-m-d') === $dayKey
            ) {
                return true;
            }
        }

        return false;
    }
}
