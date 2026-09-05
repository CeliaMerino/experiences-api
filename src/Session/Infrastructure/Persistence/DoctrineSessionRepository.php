<?php

declare(strict_types=1);

namespace App\Session\Infrastructure\Persistence;

use App\Experience\Domain\ExperienceId;
use App\Experience\Infrastructure\Persistence\ExperienceIdType;
use App\Session\Domain\Session;
use App\Session\Domain\SessionDayTaken;
use App\Session\Domain\SessionId;
use App\Session\Domain\SessionNotFound;
use App\Session\Domain\SessionRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(SessionRepository::class)]
final class DoctrineSessionRepository implements SessionRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        self::registerTypes();
    }

    public function save(Session $session): void
    {
        try {
            $this->entityManager->persist($session);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            if (!$this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            throw new SessionDayTaken($session->experienceId(), $session->startsAt()->setTime(0, 0));
        }
    }

    public function find(SessionId $id): ?Session
    {
        return $this->entityManager->find(Session::class, $id);
    }

    public function getForUpdate(SessionId $id): Session
    {
        $session = $this->entityManager->find(Session::class, $id, LockMode::PESSIMISTIC_WRITE);

        return $session ?? throw new SessionNotFound($id);
    }

    public function hasSessionOnDay(ExperienceId $experienceId, \DateTimeImmutable $day): bool
    {
        $found = $this->entityManager->getConnection()->fetchOne(
            'SELECT 1 FROM sessions WHERE experience_id = :experienceId AND session_day = :day',
            [
                'experienceId' => $experienceId->value(),
                'day' => $day->format('Y-m-d'),
            ],
        );

        return false !== $found;
    }

    private function isUniqueConstraintViolation(\Throwable $exception): bool
    {
        if ($exception instanceof UniqueConstraintViolationException) {
            return true;
        }

        $previous = $exception->getPrevious();

        return $previous instanceof \Throwable && $this->isUniqueConstraintViolation($previous);
    }

    private static function registerTypes(): void
    {
        $types = [
            SessionIdType::NAME => SessionIdType::class,
            CapacityType::NAME => CapacityType::class,
            SeatsType::NAME => SeatsType::class,
            MoneyType::NAME => MoneyType::class,
            ExperienceIdType::NAME => ExperienceIdType::class,
        ];

        foreach ($types as $name => $class) {
            if (!Type::hasType($name)) {
                Type::addType($name, $class);
            }
        }
    }
}
