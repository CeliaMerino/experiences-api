<?php

declare(strict_types=1);

namespace App\Tests\Booking\Functional;

use App\Booking\Domain\Booking;
use App\Booking\Domain\BookingId;
use App\Booking\Domain\BookingRepository;
use App\Booking\Domain\UserId;
use App\Experience\Domain\ProviderId;
use App\Shared\Domain\Mailer;
use App\Tests\Booking\SpyMailer;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class BookingNotificationTest extends WebTestCase
{
    public function testReservaConExitoProduceExactamenteUnCorreoAlContactEmail(): void
    {
        $client = $this->client();
        $mailer = $this->mailer();
        $sessionId = $this->altaSesion($client, 10);

        $response = $this->reservar($client, $sessionId, 'cliente@example.com', 3);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertCount(1, $mailer->sent());
        self::assertSame('cliente@example.com', $mailer->sent()[0]['to']);
        self::assertSame('Reserva confirmada', $mailer->sent()[0]['subject']);
    }

    public function testReservaRechazadaPorAforoNoEnviaCorreo(): void
    {
        $client = $this->client();
        $mailer = $this->mailer();
        $sessionId = $this->altaSesion($client, 2);

        $response = $this->reservar($client, $sessionId, 'cliente@example.com', 3);

        $this->assertProblemJson($response, 409, 'not-enough-seats');
        self::assertSame([], $mailer->sent());
    }

    public function testCancelacionEfectivaProduceExactamenteUnCorreoDeCancelacion(): void
    {
        $client = $this->client();
        $mailer = $this->mailer();
        $sessionId = $this->altaSesion($client, 10);
        $bookingId = $this->reservarYObtenerId($client, $sessionId, 'cliente@example.com', 4);

        $client->request('POST', '/api/bookings/'.$bookingId.'/cancellation');

        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $cancellations = array_values(array_filter(
            $mailer->sent(),
            static fn (array $message): bool => 'Reserva cancelada' === $message['subject'],
        ));
        self::assertCount(1, $cancellations);
        self::assertSame('cliente@example.com', $cancellations[0]['to']);
        self::assertStringContainsString('La v1 no contempla reembolso.', $cancellations[0]['body']);
    }

    public function testSegundaCancelacionRechazadaNoEnviaCorreo(): void
    {
        $client = $this->client();
        $mailer = $this->mailer();
        $sessionId = $this->altaSesion($client, 10);
        $bookingId = $this->reservarYObtenerId($client, $sessionId, 'cliente@example.com', 4);

        $client->request('POST', '/api/bookings/'.$bookingId.'/cancellation');
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
        $sentAfterFirst = $mailer->sent();

        $client->request('POST', '/api/bookings/'.$bookingId.'/cancellation');

        $this->assertProblemJson($client->getResponse(), 409, 'booking-already-cancelled');
        self::assertSame($sentAfterFirst, $mailer->sent());
    }

    public function testFalloAlGuardarTrasReserveNoEnviaCorreoNiInsertaFilas(): void
    {
        $client = $this->client();
        $mailer = $this->mailer();
        $sessionId = $this->altaSesion($client, 10);
        $countBefore = $this->bookingCount();

        static::getContainer()->set(BookingRepository::class, new class implements BookingRepository {
            public function save(Booking $booking): void
            {
                throw new \RuntimeException('Forced save failure.');
            }

            public function find(BookingId $id): ?Booking
            {
                return null;
            }
        });

        $response = $this->reservar($client, $sessionId, 'cliente@example.com', 3);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame([], $mailer->sent());
        self::assertSame($countBefore, $this->bookingCount());
    }

    public function testFalloDelMailerNoRevierteLaReserva(): void
    {
        $client = $this->client();
        $mailer = $this->mailer();
        $sessionId = $this->altaSesion($client, 10);
        $mailer->failOnNextSend();

        $this->reservar($client, $sessionId, 'cliente@example.com', 1);

        $bookingId = $this->connection()->fetchOne(
            'SELECT id FROM bookings WHERE session_id = ? AND status = ?',
            [$sessionId, 'confirmed'],
        );
        self::assertIsString($bookingId);

        $client->request('GET', '/api/bookings/'.$bookingId);
        $getResponse = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $getResponse->getStatusCode());

        $body = json_decode((string) $getResponse->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('confirmed', $body['status']);
    }

    private function client(): KernelBrowser
    {
        $client = static::createClient();
        $client->disableReboot();

        return $client;
    }

    private function mailer(): SpyMailer
    {
        $mailer = static::getContainer()->get(Mailer::class);
        self::assertInstanceOf(SpyMailer::class, $mailer);

        return $mailer;
    }

    private function bookingCount(): int
    {
        $count = $this->connection()->fetchOne('SELECT count(*) FROM bookings');
        self::assertNotFalse($count);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    private function reservarYObtenerId(KernelBrowser $client, string $sessionId, string $email, int $seats): string
    {
        $response = $this->reservar($client, $sessionId, $email, $seats);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $location = $response->headers->get('Location');
        self::assertNotNull($location);

        return substr($location, strlen('/api/bookings/'));
    }

    private function reservar(KernelBrowser $client, string $sessionId, string $email, int $seats): Response
    {
        $client->request(
            'POST',
            '/api/sessions/'.$sessionId.'/bookings',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'userId' => UserId::generate()->value(),
                'seats' => $seats,
                'contactEmail' => $email,
            ], \JSON_THROW_ON_ERROR),
        );

        return $client->getResponse();
    }

    private function altaSesion(KernelBrowser $client, int $capacity): string
    {
        $experienceId = $this->altaExperiencia($client);

        $client->request(
            'POST',
            '/api/experiences/'.$experienceId.'/sessions',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'startsAt' => '2026-07-01T10:00:00+00:00',
                'capacity' => $capacity,
                'priceAmount' => 1250,
                'priceCurrency' => 'EUR',
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $location = $response->headers->get('Location');
        self::assertNotNull($location);

        return substr($location, strlen('/api/sessions/'));
    }

    private function altaExperiencia(KernelBrowser $client): string
    {
        $client->request(
            'POST',
            '/api/experiences',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'providerId' => ProviderId::generate()->value(),
                'title' => 'Kayak en el Sella',
                'description' => 'Descenso del río',
                'timezone' => 'Europe/Madrid',
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $location = $response->headers->get('Location');
        self::assertNotNull($location);

        return substr($location, strlen('/api/experiences/'));
    }

    private function assertProblemJson(Response $response, int $status, string $type): void
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertStringContainsString(
            'application/problem+json',
            (string) $response->headers->get('Content-Type'),
        );

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame(['type', 'title', 'status', 'detail'], array_keys($data));
        self::assertSame($type, $data['type']);
        self::assertSame($status, $data['status']);
        self::assertIsString($data['title']);
        self::assertIsString($data['detail']);
    }
}
