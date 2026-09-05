<?php

declare(strict_types=1);

namespace App\Booking\Infrastructure\Http;

use App\Booking\Domain\BookingId;
use App\Booking\Domain\BookingNotFound;
use App\Booking\Domain\BookingRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class GetBookingController
{
    public function __construct(
        private BookingRepository $repository,
    ) {
    }

    public function __invoke(string $bookingId): JsonResponse
    {
        $id = BookingId::fromString($bookingId);
        $booking = $this->repository->find($id) ?? throw new BookingNotFound($id);

        return new JsonResponse([
            'id' => $booking->id()->value(),
            'sessionId' => $booking->sessionId()->value(),
            'userId' => $booking->userId()->value(),
            'seats' => $booking->seats()->value(),
            'status' => $booking->status()->value,
            'total' => [
                'amount' => $booking->total()->amount(),
                'currency' => $booking->total()->currency(),
            ],
        ]);
    }
}
