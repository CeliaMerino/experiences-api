<?php

declare(strict_types=1);

namespace App\Tests\Session\Functional;

use App\Experience\Domain\ExperienceId;
use App\Experience\Domain\ProviderId;
use App\Kernel;
use App\Session\Domain\Capacity;
use App\Session\Domain\Session;
use App\Session\Domain\SessionDayTaken;
use App\Session\Domain\SessionId;
use App\Session\Domain\SessionRepository;
use App\Shared\Domain\ValueObject\Money;
use App\Tests\Shared\FrozenClock;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Response;

final class SessionEndpointsTest extends WebTestCase
{
    public function testAltaValidaDevuelve201YLocationQueResponde200(): void
    {
        $client = static::createClient();
        $experienceId = $this->altaExperiencia($client);

        $response = $this->altaSesion($client, $experienceId, [
            'startsAt' => '2026-07-01T10:00:00+00:00',
            'capacity' => 12,
            'priceAmount' => 3500,
            'priceCurrency' => 'EUR',
        ]);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $location = $response->headers->get('Location');
        self::assertNotNull($location);
        self::assertMatchesRegularExpression('#^/api/sessions/[0-9a-f-]{36}$#', $location);

        $client->request('GET', $location);
        $getResponse = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $getResponse->getStatusCode());

        $body = json_decode((string) $getResponse->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame(
            ['id', 'experienceId', 'startsAt', 'capacity', 'seatsTaken', 'seatsAvailable', 'price'],
            array_keys($body),
        );
        self::assertSame($experienceId, $body['experienceId']);
        self::assertSame(12, $body['capacity']);
        self::assertSame(0, $body['seatsTaken']);
        self::assertSame(12, $body['seatsAvailable']);
        self::assertSame(['amount' => 3500, 'currency' => 'EUR'], $body['price']);
        self::assertSame(substr($location, strlen('/api/sessions/')), $body['id']);
        self::assertIsString($body['startsAt']);
        self::assertSame(
            (new \DateTimeImmutable('2026-07-01T10:00:00+00:00'))->getTimestamp(),
            (new \DateTimeImmutable($body['startsAt']))->getTimestamp(),
        );
    }

    public function testSegundaSesionElMismoDiaDevuelve409SessionDayTaken(): void
    {
        $client = static::createClient();
        $experienceId = $this->altaExperiencia($client);

        $first = $this->altaSesion($client, $experienceId, [
            'startsAt' => '2026-07-01T10:00:00+00:00',
            'capacity' => 10,
            'priceAmount' => 2500,
            'priceCurrency' => 'EUR',
        ]);
        self::assertSame(Response::HTTP_CREATED, $first->getStatusCode());

        $second = $this->altaSesion($client, $experienceId, [
            'startsAt' => '2026-07-01T18:00:00+00:00',
            'capacity' => 8,
            'priceAmount' => 3000,
            'priceCurrency' => 'EUR',
        ]);

        $this->assertProblemJson($second, 409, 'session-day-taken');
    }

    public function testFechaPasadaDevuelve422SessionInThePast(): void
    {
        $client = static::createClient();
        $experienceId = $this->altaExperiencia($client);

        $response = $this->altaSesion($client, $experienceId, [
            'startsAt' => '2026-06-15T11:00:00+00:00',
            'capacity' => 10,
            'priceAmount' => 2500,
            'priceCurrency' => 'EUR',
        ]);

        $this->assertProblemJson($response, 422, 'session-in-the-past');
    }

    public function testConsultaDeSesionInexistenteDevuelve404SessionNotFound(): void
    {
        $client = static::createClient();
        $unknownId = SessionId::generate()->value();

        $client->request('GET', '/api/sessions/'.$unknownId);

        $this->assertProblemJson($client->getResponse(), 404, 'session-not-found');
    }

    public function testAforoCeroDevuelve422InvalidValue(): void
    {
        $client = static::createClient();
        $experienceId = $this->altaExperiencia($client);

        $response = $this->altaSesion($client, $experienceId, [
            'startsAt' => '2026-07-01T10:00:00+00:00',
            'capacity' => 0,
            'priceAmount' => 2500,
            'priceCurrency' => 'EUR',
        ]);

        $this->assertProblemJson($response, 422, 'invalid-value');
    }

    public function testImporteNegativoDevuelve422InvalidValue(): void
    {
        $client = static::createClient();
        $experienceId = $this->altaExperiencia($client);

        $response = $this->altaSesion($client, $experienceId, [
            'startsAt' => '2026-07-01T10:00:00+00:00',
            'capacity' => 10,
            'priceAmount' => -1,
            'priceCurrency' => 'EUR',
        ]);

        $this->assertProblemJson($response, 422, 'invalid-value');
    }

    public function testDivisaInvalidaDevuelve422InvalidValue(): void
    {
        $client = static::createClient();
        $experienceId = $this->altaExperiencia($client);

        $response = $this->altaSesion($client, $experienceId, [
            'startsAt' => '2026-07-01T10:00:00+00:00',
            'capacity' => 10,
            'priceAmount' => 2500,
            'priceCurrency' => 'euro',
        ]);

        $this->assertProblemJson($response, 422, 'invalid-value');
    }

    public function testExperienciaInexistenteAlProgramarDevuelve404ExperienceNotFound(): void
    {
        $client = static::createClient();
        $unknownId = ExperienceId::generate()->value();

        $response = $this->altaSesion($client, $unknownId, [
            'startsAt' => '2026-07-01T10:00:00+00:00',
            'capacity' => 10,
            'priceAmount' => 2500,
            'priceCurrency' => 'EUR',
        ]);

        $this->assertProblemJson($response, 404, 'experience-not-found');
    }

    public function testInsertDirectoDuplicandoDiaEsRechazadoPorLaBaseDeDatos(): void
    {
        $client = static::createClient();
        $experienceId = $this->altaExperiencia($client);

        $created = $this->altaSesion($client, $experienceId, [
            'startsAt' => '2026-07-01T10:00:00+00:00',
            'capacity' => 10,
            'priceAmount' => 2500,
            'priceCurrency' => 'EUR',
        ]);
        self::assertSame(Response::HTTP_CREATED, $created->getStatusCode());

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $connection = $entityManager->getConnection();

        $this->expectException(UniqueConstraintViolationException::class);

        $connection->executeStatement(
            'INSERT INTO sessions (id, experience_id, starts_at, capacity, seats_taken, price)
             VALUES (:id, :experienceId, :startsAt, 10, 0, :price)',
            [
                'id' => SessionId::generate()->value(),
                'experienceId' => $experienceId,
                'startsAt' => '2026-07-01T18:00:00+00:00',
                'price' => '{"amount": 2500, "currency": "EUR"}',
            ],
        );
    }

    public function testSaveDeUnSegundoAgregadoElMismoDiaLanzaSessionDayTaken(): void
    {
        $client = static::createClient();
        $experienceId = ExperienceId::fromString($this->altaExperiencia($client));
        $repository = static::getContainer()->get(SessionRepository::class);
        self::assertInstanceOf(SessionRepository::class, $repository);
        $clock = FrozenClock::at('2026-06-15T12:00:00+00:00');

        $repository->save(Session::schedule(
            SessionId::generate(),
            $experienceId,
            new \DateTimeImmutable('2026-07-02T10:00:00+02:00'),
            Capacity::of(10),
            Money::of(2500, 'EUR'),
            $clock,
        ));

        $this->expectException(SessionDayTaken::class);

        $repository->save(Session::schedule(
            SessionId::generate(),
            $experienceId,
            new \DateTimeImmutable('2026-07-02T18:00:00+02:00'),
            Capacity::of(8),
            Money::of(3000, 'EUR'),
            $clock,
        ));
    }

    public function testDosAltasConcurrentesDelMismoDiaDevuelven201Y409Sin500(): void
    {
        $this->migrateDefaultDatabase();

        $experienceLocation = $this->curlJson(
            'http://nginx/api/experiences',
            json_encode([
                'providerId' => ProviderId::generate()->value(),
                'title' => 'Kayak en el Sella',
                'description' => 'Descenso del río',
                'timezone' => 'Europe/Madrid',
            ], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(201, $experienceLocation['status']);
        self::assertNotNull($experienceLocation['location']);
        self::assertMatchesRegularExpression('#^/api/experiences/[0-9a-f-]{36}$#', $experienceLocation['location']);

        $experienceId = substr($experienceLocation['location'], strlen('/api/experiences/'));
        $payload = json_encode([
            'startsAt' => '2030-07-01T10:00:00+00:00',
            'capacity' => 10,
            'priceAmount' => 2500,
            'priceCurrency' => 'EUR',
        ], \JSON_THROW_ON_ERROR);

        $url = 'http://nginx/api/experiences/'.$experienceId.'/sessions';
        $multi = curl_multi_init();
        self::assertNotFalse($multi);

        $handles = [];
        for ($i = 0; $i < 2; ++$i) {
            $handle = curl_init($url);
            self::assertNotFalse($handle);
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
            ]);
            curl_multi_add_handle($multi, $handle);
            $handles[] = $handle;
        }

        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi);
            }
        } while ($running && CURLM_OK === $status);

        $codes = [];
        foreach ($handles as $handle) {
            $error = curl_error($handle);
            self::assertSame('', $error, $error);
            $codes[] = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }
        curl_multi_close($multi);

        sort($codes);
        self::assertSame([201, 409], $codes);
        self::assertNotContains(500, $codes);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function altaSesion(KernelBrowser $client, string $experienceId, array $payload): Response
    {
        $client->request(
            'POST',
            '/api/experiences/'.$experienceId.'/sessions',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        return $client->getResponse();
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

    private function migrateDefaultDatabase(): void
    {
        $kernel = new Kernel('dev', false);
        $kernel->boot();

        try {
            $application = new Application($kernel);
            $application->setAutoExit(false);
            $status = $application->run(
                new ArrayInput([
                    'command' => 'doctrine:migrations:migrate',
                    '--no-interaction' => true,
                ]),
                new NullOutput(),
            );
            self::assertSame(0, $status);
        } finally {
            $kernel->shutdown();
        }
    }

    /**
     * @return array{status: int, body: string, location: ?string}
     */
    private function curlJson(string $url, string $payload): array
    {
        $handle = curl_init($url);
        self::assertNotFalse($handle);

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
        ]);

        $raw = curl_exec($handle);
        self::assertIsString($raw, curl_error($handle));

        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $headerSize = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $headers = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        curl_close($handle);

        $location = null;
        if (1 === preg_match('/^Location:\s*(.+)$/im', $headers, $matches)) {
            $location = trim($matches[1]);
        }

        return ['status' => $status, 'body' => $body, 'location' => $location];
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
