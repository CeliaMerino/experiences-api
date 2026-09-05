<?php

declare(strict_types=1);

namespace App\Tests\Experience;

use App\Experience\Domain\Experience;
use App\Experience\Domain\ExperienceId;
use App\Experience\Domain\ExperienceNotFound;
use App\Experience\Domain\ExperienceRepository;

final class InMemoryExperienceRepository implements ExperienceRepository
{
    /** @var array<string, Experience> */
    private array $items = [];

    public function save(Experience $experience): void
    {
        $this->items[$experience->id()->value()] = $experience;
    }

    public function find(ExperienceId $id): ?Experience
    {
        return $this->items[$id->value()] ?? null;
    }

    public function get(ExperienceId $id): Experience
    {
        return $this->find($id) ?? throw new ExperienceNotFound($id);
    }
}
