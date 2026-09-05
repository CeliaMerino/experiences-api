<?php

declare(strict_types=1);

namespace App\Booking\Infrastructure\Persistence;

use App\Booking\Domain\Booking;
use App\Booking\Domain\BookingId;
use App\Booking\Domain\BookingRepository;
use App\Session\Infrastructure\Persistence\MoneyType;
use App\Session\Infrastructure\Persistence\SeatsType;
use App\Session\Infrastructure\Persistence\SessionIdType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(BookingRepository::class)]
final class DoctrineBookingRepository implements BookingRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        self::registerTypes();
    }

    public function save(Booking $booking): void
    {
        $this->entityManager->persist($booking);
        $this->entityManager->flush();
    }

    public function find(BookingId $id): ?Booking
    {
        return $this->entityManager->find(Booking::class, $id);
    }

    private static function registerTypes(): void
    {
        $types = [
            BookingIdType::NAME => BookingIdType::class,
            UserIdType::NAME => UserIdType::class,
            ContactEmailType::NAME => ContactEmailType::class,
            SessionIdType::NAME => SessionIdType::class,
            SeatsType::NAME => SeatsType::class,
            MoneyType::NAME => MoneyType::class,
        ];

        foreach ($types as $name => $class) {
            if (!Type::hasType($name)) {
                Type::addType($name, $class);
            }
        }
    }
}
