<?php

declare(strict_types=1);

namespace App\Session\Application\Create;

final readonly class CreateSessionCommand
{
    public function __construct(
        public string $experienceId,
        public string $startsAt,
        public int $capacity,
        public int $priceAmount,
        public string $priceCurrency,
    ) {
    }
}
