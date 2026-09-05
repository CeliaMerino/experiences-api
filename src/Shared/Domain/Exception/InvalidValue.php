<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

class InvalidValue extends DomainError
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function errorType(): string
    {
        return 'invalid-value';
    }
}
