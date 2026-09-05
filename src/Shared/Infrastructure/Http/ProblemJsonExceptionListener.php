<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Exception\Conflict;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\Exception\NotFound;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ProblemJsonExceptionListener implements EventSubscriberInterface
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        [$status, $type, $detail] = $this->map($throwable);

        $response = new JsonResponse([
            'type' => $type,
            'title' => Response::$statusTexts[$status] ?? 'Unknown',
            'status' => $status,
            'detail' => $detail,
        ], $status, ['Content-Type' => 'application/problem+json']);

        $event->setResponse($response);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -1],
        ];
    }

    /**
     * @return array{0: int, 1: string, 2: string}
     */
    private function map(\Throwable $throwable): array
    {
        return match (true) {
            $throwable instanceof InvalidValue => [422, $throwable->errorType(), $throwable->getMessage()],
            $throwable instanceof NotFound => [404, $throwable->errorType(), $throwable->getMessage()],
            $throwable instanceof Conflict => [409, $throwable->errorType(), $throwable->getMessage()],
            $throwable instanceof MalformedJson => [400, $throwable->errorType(), $throwable->getMessage()],
            $throwable instanceof UnsupportedMediaType => [415, $throwable->errorType(), $throwable->getMessage()],
            default => [500, 'about:blank', 'Internal server error'],
        };
    }
}
