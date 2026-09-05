<?php

declare(strict_types=1);

namespace App\Experience\Infrastructure\Persistence;

use App\Experience\Domain\Experience;
use App\Experience\Domain\ExperienceId;
use App\Experience\Domain\ExperienceNotFound;
use App\Experience\Domain\ExperienceRepository;
use App\Shared\Infrastructure\Persistence\Doctrine\DateTimeZoneType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ExperienceRepository::class)]
final class DoctrineExperienceRepository implements ExperienceRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        self::registerTypes();
    }

    public function save(Experience $experience): void
    {
        $this->entityManager->persist($experience);
        $this->entityManager->flush();
    }

    public function find(ExperienceId $id): ?Experience
    {
        return $this->entityManager->find(Experience::class, $id);
    }

    public function get(ExperienceId $id): Experience
    {
        return $this->find($id) ?? throw new ExperienceNotFound($id);
    }

    private static function registerTypes(): void
    {
        $types = [
            ExperienceIdType::NAME => ExperienceIdType::class,
            ProviderIdType::NAME => ProviderIdType::class,
            TitleType::NAME => TitleType::class,
            DescriptionType::NAME => DescriptionType::class,
            DateTimeZoneType::NAME => DateTimeZoneType::class,
        ];

        foreach ($types as $name => $class) {
            if (!Type::hasType($name)) {
                Type::addType($name, $class);
            }
        }
    }
}
