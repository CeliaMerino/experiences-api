<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Exception\Conflict;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\Exception\NotFound;
use Symfony\Component\HttpFoundation\Request;

final class ProblemJsonTestController
{
    public function __invoke(Request $request): never
    {
        $name = $request->request->get('exception');
        if (!\is_string($name) || '' === $name) {
            throw new InvalidValue('Missing exception key.');
        }

        throw match ($name) {
            'conflict' => new class('Example conflict.') extends Conflict {
                public function errorType(): string
                {
                    return 'example-conflict';
                }
            },
            'not-found' => new class('Example not found.') extends NotFound {
                public function errorType(): string
                {
                    return 'example-not-found';
                }
            },
            'invalid-value' => new InvalidValue('Example invalid value.'),
            'runtime' => new \RuntimeException('secret-internal-message'),
            default => new InvalidValue(sprintf('Unknown exception key: "%s".', $name)),
        };
    }
}
