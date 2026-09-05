<?php

declare(strict_types=1);

namespace App\Experience\Infrastructure\Http;

use App\Experience\Application\Create\CreateExperienceCommand;
use App\Experience\Domain\ExperienceId;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[AsController]
final class CreateExperienceController
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        try {
            $envelope = $this->commandBus->dispatch(new CreateExperienceCommand(
                $request->request->getString('providerId'),
                $request->request->getString('title'),
                $request->request->getString('description'),
                $request->request->getString('timezone'),
            ));
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious() ?? $exception;
        }

        $stamp = $envelope->last(HandledStamp::class);
        if (null === $stamp) {
            throw new \LogicException('CreateExperienceCommand was not handled.');
        }

        /** @var ExperienceId $id */
        $id = $stamp->getResult();

        return new Response(status: Response::HTTP_CREATED, headers: [
            'Location' => '/api/experiences/'.$id->value(),
        ]);
    }
}
