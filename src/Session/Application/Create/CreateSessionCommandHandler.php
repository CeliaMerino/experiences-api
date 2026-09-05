<?php

declare(strict_types=1);

namespace App\Session\Application\Create;

use App\Experience\Domain\ExperienceId;
use App\Experience\Domain\ExperienceRepository;
use App\Session\Domain\Capacity;
use App\Session\Domain\Service\SessionScheduler;
use App\Session\Domain\SessionId;
use App\Session\Domain\SessionRepository;
use App\Shared\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateSessionCommandHandler
{
    public function __construct(
        private ExperienceRepository $experiences,
        private SessionRepository $sessions,
        private SessionScheduler $scheduler,
    ) {
    }

    public function __invoke(CreateSessionCommand $command): SessionId
    {
        $experience = $this->experiences->get(ExperienceId::fromString($command->experienceId));

        $session = $this->scheduler->schedule(
            $experience,
            new \DateTimeImmutable($command->startsAt),
            Capacity::of($command->capacity),
            Money::of($command->priceAmount, $command->priceCurrency),
        );

        $this->sessions->save($session);

        return $session->id();
    }
}
