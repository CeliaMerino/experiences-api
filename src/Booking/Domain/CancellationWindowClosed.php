<?php

declare(strict_types=1);

namespace App\Booking\Domain;

use App\Shared\Domain\Exception\Conflict;

final class CancellationWindowClosed extends Conflict
{
    public function __construct(BookingId $id)
    {
        parent::__construct(sprintf(
            'Cancellation window for booking "%s" is closed.',
            $id->value(),
        ));
    }

    public function errorType(): string
    {
        return 'cancellation-window-closed';
    }
}
