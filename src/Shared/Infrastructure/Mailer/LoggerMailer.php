<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Mailer;

use App\Shared\Domain\Mailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(Mailer::class, public: true, when: ['dev', 'prod'])]
final class LoggerMailer implements Mailer
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function send(string $to, string $subject, string $body): void
    {
        $this->logger->info('Mailer send', [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
        ]);
    }
}
