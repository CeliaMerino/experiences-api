<?php

declare(strict_types=1);

namespace App\Session\Domain;

use App\Shared\Domain\Exception\Conflict;

final class NotEnoughSeats extends Conflict
{
    public function __construct(SessionId $id, Seats $requested, Seats $available)
    {
        parent::__construct(sprintf(
            'Session "%s" has %d seats available, %d requested.',
            $id->value(),
            $available->value(),
            $requested->value(),
        ));
    }

    public function errorType(): string
    {
        return 'not-enough-seats';
    }
}
