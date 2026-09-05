<?php

declare(strict_types=1);

namespace App\Experience\Application\Create;

final readonly class CreateExperienceCommand
{
    public function __construct(
        public string $providerId,
        public string $title,
        public string $description,
        public string $timezone,
    ) {
    }
}
