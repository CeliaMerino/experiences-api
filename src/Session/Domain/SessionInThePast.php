<?php

declare(strict_types=1);

namespace App\Session\Domain;

use App\Shared\Domain\Exception\InvalidValue;

final class SessionInThePast extends InvalidValue
{
    public function __construct(\DateTimeImmutable $startsAt)
    {
        parent::__construct(sprintf(
            'Session start "%s" is in the past.',
            $startsAt->format(\DateTimeInterface::ATOM),
        ));
    }

    public function errorType(): string
    {
        return 'session-in-the-past';
    }
}
