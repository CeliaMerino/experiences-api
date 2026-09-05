<?php

declare(strict_types=1);

namespace App\Experience\Infrastructure\Http;

use App\Experience\Domain\ExperienceId;
use App\Experience\Domain\ExperienceRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class GetExperienceController
{
    public function __construct(
        private ExperienceRepository $repository,
    ) {
    }

    public function __invoke(string $experienceId): JsonResponse
    {
        $experience = $this->repository->get(ExperienceId::fromString($experienceId));

        return new JsonResponse([
            'id' => $experience->id()->value(),
            'providerId' => $experience->providerId()->value(),
            'title' => $experience->title()->value(),
            'description' => $experience->description()->value(),
            'timezone' => $experience->timezone()->getName(),
        ]);
    }
}
