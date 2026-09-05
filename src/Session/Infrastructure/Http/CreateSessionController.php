<?php

declare(strict_types=1);

namespace App\Session\Infrastructure\Http;

use App\Session\Application\Create\CreateSessionCommand;
use App\Session\Domain\SessionId;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[AsController]
final class CreateSessionController
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $experienceId): Response
    {
        try {
            $envelope = $this->commandBus->dispatch(new CreateSessionCommand(
                $experienceId,
                $request->request->getString('startsAt'),
                $request->request->getInt('capacity'),
                $request->request->getInt('priceAmount'),
                $request->request->getString('priceCurrency'),
            ));
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious() ?? $exception;
        }

        $stamp = $envelope->last(HandledStamp::class);
        if (null === $stamp) {
            throw new \LogicException('CreateSessionCommand was not handled.');
        }

        /** @var SessionId $id */
        $id = $stamp->getResult();

        return new Response(status: Response::HTTP_CREATED, headers: [
            'Location' => '/api/sessions/'.$id->value(),
        ]);
    }
}
