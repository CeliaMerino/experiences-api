<?php

declare(strict_types=1);

namespace App\Booking\Infrastructure\Http;

use App\Booking\Application\Book\BookSeatsCommand;
use App\Booking\Domain\BookingId;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[AsController]
final class BookSeatsController
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $sessionId): Response
    {
        try {
            $envelope = $this->commandBus->dispatch(new BookSeatsCommand(
                $sessionId,
                $request->request->getString('userId'),
                $request->request->getInt('seats'),
                $request->request->getString('contactEmail'),
            ));
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious() ?? $exception;
        }

        $stamp = $envelope->last(HandledStamp::class);
        if (null === $stamp) {
            throw new \LogicException('BookSeatsCommand was not handled.');
        }

        /** @var BookingId $id */
        $id = $stamp->getResult();

        return new Response(status: Response::HTTP_CREATED, headers: [
            'Location' => '/api/bookings/'.$id->value(),
        ]);
    }
}
