<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

abstract class DomainError extends \DomainException
{
    abstract public function errorType(): string;
}
