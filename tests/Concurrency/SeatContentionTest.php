<?php

declare(strict_types=1);

namespace App\Tests\Concurrency;

use App\Experience\Domain\ProviderId;
use App\Kernel;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class SeatContentionTest extends TestCase
{
    public function testCincuentaReservasSimultaneasDeUnaPlazaSobreAforoDiezDanDiezConfirmadasYCuarentaConflictos(): void
    {
        $this->migrateDefaultDatabase();

        $experience = $this->curlJson(
            'http://nginx/api/experiences',
            json_encode([
                'providerId' => ProviderId::generate()->value(),
                'title' => 'Kayak en el Sella',
                'description' => 'Descenso del río',
                'timezone' => 'Europe/Madrid',
            ], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(201, $experience['status']);
        self::assertNotNull($experience['location']);
        self::assertMatchesRegularExpression('#^/api/experiences/[0-9a-f-]{36}$#', $experience['location']);

        $experienceId = substr($experience['location'], strlen('/api/experiences/'));

        $session = $this->curlJson(
            'http://nginx/api/experiences/'.$experienceId.'/sessions',
            json_encode([
                'startsAt' => '2030-07-01T10:00:00+00:00',
                'capacity' => 10,
                'priceAmount' => 2500,
                'priceCurrency' => 'EUR',
            ], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(201, $session['status']);
        self::assertNotNull($session['location']);
        self::assertMatchesRegularExpression('#^/api/sessions/[0-9a-f-]{36}$#', $session['location']);

        $sessionId = substr($session['location'], strlen('/api/sessions/'));

        $payloads = [];
        for ($i = 0; $i < 50; ++$i) {
            $payloads[] = json_encode([
                'userId' => Uuid::generate()->value(),
                'seats' => 1,
                'contactEmail' => sprintf('cliente%d@example.com', $i),
            ], \JSON_THROW_ON_ERROR);
        }

        $codes = $this->curlMultiPost(
            'http://nginx/api/sessions/'.$sessionId.'/bookings',
            $payloads,
        );

        $counts = array_count_values($codes);
        self::assertSame(10, $counts[201] ?? 0, 'Expected exactly 10 responses with status 201, got: '.json_encode($counts));
        self::assertSame(40, $counts[409] ?? 0, 'Expected exactly 40 responses with status 409, got: '.json_encode($counts));
        self::assertNotContains(500, $codes);

        $sessionGet = $this->curlGet('http://nginx/api/sessions/'.$sessionId);
        self::assertSame(200, $sessionGet['status']);

        $sessionBody = json_decode($sessionGet['body'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($sessionBody);
        self::assertSame(10, $sessionBody['seatsTaken']);
        self::assertSame(0, $sessionBody['seatsAvailable']);

        self::assertSame(10, $this->countConfirmedBookings($sessionId));
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
    private function curlJson(string $url, string $jsonBody): array
    {
        $handle = curl_init($url);
        self::assertNotFalse($handle);

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
        ]);

        return $this->readCurlResponse($handle);
    }

    /**
     * @return array{status: int, body: string, location: ?string}
     */
    private function curlGet(string $url): array
    {
        $handle = curl_init($url);
        self::assertNotFalse($handle);

        curl_setopt_array($handle, [
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
        ]);

        return $this->readCurlResponse($handle);
    }

    /**
     * @return array{status: int, body: string, location: ?string}
     */
    private function readCurlResponse(\CurlHandle $handle): array
    {
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

    /**
     * @param list<string> $payloads
     *
     * @return list<int>
     */
    private function curlMultiPost(string $url, array $payloads): array
    {
        $multi = curl_multi_init();
        self::assertNotFalse($multi);

        $handles = [];
        foreach ($payloads as $payload) {
            $handle = curl_init($url);
            self::assertNotFalse($handle);
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 60,
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

        return $codes;
    }

    private function countConfirmedBookings(string $sessionId): int
    {
        $url = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
        self::assertIsString($url);
        self::assertNotSame('', $url);

        $params = (new DsnParser(['postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql']))->parse($url);
        $connection = DriverManager::getConnection($params);

        try {
            $count = $connection->fetchOne(
                "SELECT count(*) FROM bookings WHERE session_id = :sessionId AND status = 'confirmed'",
                ['sessionId' => $sessionId],
            );
            self::assertNotFalse($count);
            self::assertIsNumeric($count);

            return (int) $count;
        } finally {
            $connection->close();
        }
    }
}
