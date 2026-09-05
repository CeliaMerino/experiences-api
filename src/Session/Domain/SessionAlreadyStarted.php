<?php

declare(strict_types=1);

namespace App\Session\Domain;

use App\Shared\Domain\Exception\Conflict;

final class SessionAlreadyStarted extends Conflict
{
    public function __construct(SessionId $id)
    {
        parent::__construct(sprintf('Session "%s" has already started.', $id->value()));
    }

    public function errorType(): string
    {
        return 'session-already-started';
    }
}
