<?php

declare(strict_types=1);

namespace App\Experience\Domain;

use App\Shared\Domain\Exception\InvalidValue;

final class Experience
{
    private function __construct(
        private ExperienceId $id,
        private ProviderId $providerId,
        private Title $title,
        private Description $description,
        private \DateTimeZone $timezone,
    ) {
    }

    public static function create(
        ExperienceId $id,
        ProviderId $providerId,
        Title $title,
        Description $description,
        string $timezone,
    ): self {
        try {
            $zone = new \DateTimeZone($timezone);
        } catch (\Exception $exception) {
            throw new InvalidValue(sprintf('Invalid timezone: "%s".', $timezone), previous: $exception);
        }

        return new self($id, $providerId, $title, $description, $zone);
    }

    public function id(): ExperienceId
    {
        return $this->id;
    }

    public function providerId(): ProviderId
    {
        return $this->providerId;
    }

    public function title(): Title
    {
        return $this->title;
    }

    public function description(): Description
    {
        return $this->description;
    }

    public function timezone(): \DateTimeZone
    {
        return $this->timezone;
    }
}
