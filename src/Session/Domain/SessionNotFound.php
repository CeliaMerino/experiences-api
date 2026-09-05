<?php

declare(strict_types=1);

namespace App\Session\Domain;

use App\Shared\Domain\Exception\NotFound;

final class SessionNotFound extends NotFound
{
    public function __construct(SessionId $id)
    {
        parent::__construct(sprintf('Session "%s" not found.', $id->value()));
    }

    public function errorType(): string
    {
        return 'session-not-found';
    }
}
