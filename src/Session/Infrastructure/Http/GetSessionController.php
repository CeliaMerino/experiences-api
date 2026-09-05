<?php

declare(strict_types=1);

namespace App\Session\Infrastructure\Http;

use App\Session\Domain\SessionId;
use App\Session\Domain\SessionNotFound;
use App\Session\Domain\SessionRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class GetSessionController
{
    public function __construct(
        private SessionRepository $repository,
    ) {
    }

    public function __invoke(string $sessionId): JsonResponse
    {
        $id = SessionId::fromString($sessionId);
        $session = $this->repository->find($id) ?? throw new SessionNotFound($id);

        return new JsonResponse([
            'id' => $session->id()->value(),
            'experienceId' => $session->experienceId()->value(),
            'startsAt' => $session->startsAt()->format(\DateTimeInterface::ATOM),
            'capacity' => $session->capacity()->value(),
            'seatsTaken' => $session->seatsTaken()->value(),
            'seatsAvailable' => $session->seatsAvailable()->value(),
            'price' => [
                'amount' => $session->price()->amount(),
                'currency' => $session->price()->currency(),
            ],
        ]);
    }
}
