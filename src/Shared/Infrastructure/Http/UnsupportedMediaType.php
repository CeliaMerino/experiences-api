<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final class UnsupportedMediaType extends \RuntimeException
{
    public function __construct(string $message = 'Content-Type must be application/json.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function errorType(): string
    {
        return 'unsupported-media-type';
    }
}
