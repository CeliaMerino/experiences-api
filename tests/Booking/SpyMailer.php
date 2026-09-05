<?php

declare(strict_types=1);

namespace App\Tests\Booking;

use App\Shared\Domain\Mailer;

final class SpyMailer implements Mailer
{
    /** @var list<array{to: string, subject: string, body: string}> */
    private array $sent = [];

    private bool $failOnNextSend = false;

    public function send(string $to, string $subject, string $body): void
    {
        if ($this->failOnNextSend) {
            $this->failOnNextSend = false;

            throw new \RuntimeException('Forced mailer failure.');
        }

        $this->sent[] = [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * @return list<array{to: string, subject: string, body: string}>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    public function failOnNextSend(): void
    {
        $this->failOnNextSend = true;
    }
}
