<?php

declare(strict_types=1);

namespace App\Experience\Application\Create;

use App\Experience\Domain\Description;
use App\Experience\Domain\Experience;
use App\Experience\Domain\ExperienceId;
use App\Experience\Domain\ExperienceRepository;
use App\Experience\Domain\ProviderId;
use App\Experience\Domain\Title;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateExperienceCommandHandler
{
    public function __construct(
        private ExperienceRepository $repository,
    ) {
    }

    public function __invoke(CreateExperienceCommand $command): ExperienceId
    {
        $id = ExperienceId::generate();

        $experience = Experience::create(
            $id,
            ProviderId::fromString($command->providerId),
            Title::of($command->title),
            Description::of($command->description),
            $command->timezone,
        );

        $this->repository->save($experience);

        return $id;
    }
}
