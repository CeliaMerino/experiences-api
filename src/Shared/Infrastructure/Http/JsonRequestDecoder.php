<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class JsonRequestDecoder implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $content = $request->getContent();
        if ('' === $content) {
            return;
        }

        $contentType = strtolower(trim($request->headers->get('Content-Type') ?? ''));
        if (!str_starts_with($contentType, 'application/json')) {
            throw new UnsupportedMediaType();
        }

        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new MalformedJson(previous: $exception);
        }

        if (!\is_array($data)) {
            throw new MalformedJson();
        }

        $payload = [];
        foreach ($data as $key => $value) {
            if (!\is_string($key)) {
                throw new MalformedJson();
            }
            $payload[$key] = $value;
        }

        $request->request->replace($payload);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}
