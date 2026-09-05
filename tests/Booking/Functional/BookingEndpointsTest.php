<?php

declare(strict_types=1);

namespace App\Tests\Booking\Functional;

use App\Booking\Domain\BookingId;
use App\Booking\Domain\UserId;
use App\Experience\Domain\ExperienceId;
use App\Experience\Domain\ProviderId;
use App\Session\Domain\Capacity;
use App\Session\Domain\Session;
use App\Session\Domain\SessionId;
use App\Session\Domain\SessionRepository;
use App\Shared\Domain\ValueObject\Money;
use App\Tests\Shared\FrozenClock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class BookingEndpointsTest extends WebTestCase
{
    public function testReservaValidaDevuelve201YActualizaElContadorDeLaSesion(): void
    {
        $client = static::createClient();
        $experienceId = $this->altaExperiencia($client);
        $sessionId = $this->altaSesion($client, $experienceId, [
            'startsAt' => '2026-07-01T10:00:00+00:00',
            'capacity' => 10,
            'priceAmount' => 1250,
            'priceCurrency' => 'EUR',
        ]);
        $userId = UserId::generate()->value();

        $response = $this->reservar($client, $sessionId, [
            'userId' => $userId,
            'seats' => 3,
            'contactEmail' => 'cliente@example.com',
        ]);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $location = $response->headers->get('Location');
        self::assertNotNull($location);
        self::assertMatchesRegularExpression('#^/api/bookings/[0-9a-f-]{36}$#', $location);

        $client->request('GET', $location);
        $getResponse = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $getResponse->getStatusCode());

        $body = json_decode((string) $getResponse->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame(
            ['id', 'sessionId', 'userId', 'seats', 'status', 'total'],
            array_keys($body),
        );
        self::assertArrayNotHasKey('contactEmail', $body);
        self::assertSame($sessionId, $body['sessionId']);
        self::assertSame($userId, $body['userId']);
        self::assertSame(3, $body['seats']);
        self::assertSame('confirmed', $body['status']);
        self::assertSame(['amount' => 3750, 'currency' => 'EUR'], $body['total']);
        self::assertSame(substr($location, strlen('/api/bookings/')), $body['id']);

        $client->request('GET', '/api/sessions/'.$sessionId);
        $sessionBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($sessionBody);
        self::assertSame(3, $sessionBody['seatsTaken']);
        self::assertSame(7, $sessionBody['seatsAvailable']);
    }

    public function testPlazasInsuficientesDevuelve409NotEnoughSeats(): void
    {
        $client = static::createClient();
        $experienceId = $this->altaExperiencia($client);
        $sessionId = $this->altaSesion($client, $experienceId, [
            'startsAt' => '2026-07-01T10:00:00+00:00',
            'capacity' => 2,
            'priceAmount' => 1250,
            'priceCurrency' => 'EUR',
        ]);

        $response = $this->reservar($client, $sessionId, [
            'userId' => UserId::generate()->value(),
            'seats' => 3,
            'contactEmail' => 'cliente@example.com',
        ]);

        $this->assertProblemJson($response, 409, 'not-enough-seats');

        $client->request('GET', '/api/sessions/'.$sessionId);
        $sessionBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($sessionBody);
        self::assertSame(0, $sessionBody['seatsTaken']);
    }

    public function testSesionYaEmpezadaDevuelve409SessionAlreadyStarted(): void
    {
        $client = static::createClient();
        $experienceId = ExperienceId::fromString($this->altaExperiencia($client));
        $repository = static::getContainer()->get(SessionRepository::class);
        self::assertInstanceOf(SessionRepository::class, $repository);

        $sessionId = SessionId::generate();
        $repository->save(Session::schedule(
            $sessionId,
            $experienceId,
            new \DateTimeImmutable('2026-06-15T11:00:00+00:00'),
            Capacity::of(10),
            Money::of(1250, 'EUR'),
            FrozenClock::at('2026-06-14T12:00:00+00:00'),
        ));

        $response = $this->reservar($client, $sessionId->value(), [
            'userId' => UserId::generate()->value(),
            'seats' => 1,
            'contactEmail' => 'cliente@example.com',
        ]);

        $this->assertProblemJson($response, 409, 'session-already-started');
    }

    public function testCancelacionValidaDevuelve204YLaSegunda409(): void
    {
        $client = static::createClient();
        $experienceId = $this->altaExperiencia($client);
        $sessionId = $this->altaSesion($client, $experienceId, [
            'startsAt' => '2026-07-01T10:00:00+00:00',
            'capacity' => 10,
            'priceAmount' => 1250,
            'priceCurrency' => 'EUR',
        ]);

        $book = $this->reservar($client, $sessionId, [
            'userId' => UserId::generate()->value(),
            'seats' => 4,
            'contactEmail' => 'cliente@example.com',
        ]);
        self::assertSame(Response::HTTP_CREATED, $book->getStatusCode());
        $location = $book->headers->get('Location');
        self::assertNotNull($location);
        $bookingId = substr($location, strlen('/api/bookings/'));

        $client->request('POST', '/api/bookings/'.$bookingId.'/cancellation');
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/sessions/'.$sessionId);
        $sessionBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($sessionBody);
        self::assertSame(0, $sessionBody['seatsTaken']);

        $client->request('GET', $location);
        $bookingBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($bookingBody);
        self::assertSame('cancelled', $bookingBody['status']);

        $client->request('POST', '/api/bookings/'.$bookingId.'/cancellation');
        $this->assertProblemJson($client->getResponse(), 409, 'booking-already-cancelled');

        $client->request('GET', '/api/sessions/'.$sessionId);
        $sessionAfterSecond = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($sessionAfterSecond);
        self::assertSame(0, $sessionAfterSecond['seatsTaken']);
    }

    public function testSesionInexistenteAlReservarDevuelve404(): void
    {
        $client = static::createClient();

        $response = $this->reservar($client, SessionId::generate()->value(), [
            'userId' => UserId::generate()->value(),
            'seats' => 1,
            'contactEmail' => 'cliente@example.com',
        ]);

        $this->assertProblemJson($response, 404, 'session-not-found');
    }

    public function testConsultaDeReservaInexistenteDevuelve404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/bookings/'.BookingId::generate()->value());

        $this->assertProblemJson($client->getResponse(), 404, 'booking-not-found');
    }

    public function testCancelacionDeReservaInexistenteDevuelve404(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/bookings/'.BookingId::generate()->value().'/cancellation');

        $this->assertProblemJson($client->getResponse(), 404, 'booking-not-found');
    }

    public function testPlazasCeroDevuelve422InvalidValue(): void
    {
        $client = static::createClient();
        $experienceId = $this->altaExperiencia($client);
        $sessionId = $this->altaSesion($client, $experienceId, [
            'startsAt' => '2026-07-01T10:00:00+00:00',
            'capacity' => 10,
            'priceAmount' => 1250,
            'priceCurrency' => 'EUR',
        ]);

        $response = $this->reservar($client, $sessionId, [
            'userId' => UserId::generate()->value(),
            'seats' => 0,
            'contactEmail' => 'cliente@example.com',
        ]);

        $this->assertProblemJson($response, 422, 'invalid-value');
    }

    public function testCorreoInvalidoDevuelve422InvalidValue(): void
    {
        $client = static::createClient();
        $experienceId = $this->altaExperiencia($client);
        $sessionId = $this->altaSesion($client, $experienceId, [
            'startsAt' => '2026-07-01T10:00:00+00:00',
            'capacity' => 10,
            'priceAmount' => 1250,
            'priceCurrency' => 'EUR',
        ]);

        $response = $this->reservar($client, $sessionId, [
            'userId' => UserId::generate()->value(),
            'seats' => 2,
            'contactEmail' => 'no-es-un-email',
        ]);

        $this->assertProblemJson($response, 422, 'invalid-value');
    }

    public function testVentanaDeCancelacionCerradaDevuelve409(): void
    {
        $client = static::createClient();
        $experienceId = $this->altaExperiencia($client);
        $sessionId = $this->altaSesion($client, $experienceId, [
            'startsAt' => '2026-06-16T11:59:00+00:00',
            'capacity' => 10,
            'priceAmount' => 1250,
            'priceCurrency' => 'EUR',
        ]);

        $book = $this->reservar($client, $sessionId, [
            'userId' => UserId::generate()->value(),
            'seats' => 2,
            'contactEmail' => 'cliente@example.com',
        ]);
        self::assertSame(Response::HTTP_CREATED, $book->getStatusCode());
        $location = $book->headers->get('Location');
        self::assertNotNull($location);
        $bookingId = substr($location, strlen('/api/bookings/'));

        $client->request('POST', '/api/bookings/'.$bookingId.'/cancellation');

        $this->assertProblemJson($client->getResponse(), 409, 'cancellation-window-closed');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function reservar(KernelBrowser $client, string $sessionId, array $payload): Response
    {
        $client->request(
            'POST',
            '/api/sessions/'.$sessionId.'/bookings',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        return $client->getResponse();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function altaSesion(KernelBrowser $client, string $experienceId, array $payload): string
    {
        $client->request(
            'POST',
            '/api/experiences/'.$experienceId.'/sessions',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
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
