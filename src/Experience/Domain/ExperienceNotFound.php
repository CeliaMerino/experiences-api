<?php

declare(strict_types=1);

namespace App\Experience\Domain;

use App\Shared\Domain\Exception\NotFound;

final class ExperienceNotFound extends NotFound
{
    public function __construct(ExperienceId $id)
    {
        parent::__construct(sprintf('Experience "%s" not found.', $id->value()));
    }

    public function errorType(): string
    {
        return 'experience-not-found';
    }
}
