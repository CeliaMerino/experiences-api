<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final class MalformedJson extends \RuntimeException
{
    public function __construct(string $message = 'Request body is not valid JSON.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function errorType(): string
    {
        return 'malformed-json';
    }
}
