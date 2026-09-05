<?php

declare(strict_types=1);

namespace App\Tests\Experience\Functional;

use App\Experience\Domain\ExperienceId;
use App\Experience\Domain\ProviderId;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ExperienceEndpointsTest extends WebTestCase
{
    public function testAltaValidaDevuelve201YLocationQueResponde200ConRepresentacion(): void
    {
        $client = static::createClient();
        $providerId = ProviderId::generate()->value();

        $client->request(
            'POST',
            '/api/experiences',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'providerId' => $providerId,
                'title' => 'Kayak en el Sella',
                'description' => 'Descenso del río',
                'timezone' => 'Europe/Madrid',
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $location = $response->headers->get('Location');
        self::assertNotNull($location);
        self::assertMatchesRegularExpression('#^/api/experiences/[0-9a-f-]{36}$#', $location);

        $client->request('GET', $location);
        $getResponse = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $getResponse->getStatusCode());

        $body = json_decode((string) $getResponse->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame(['id', 'providerId', 'title', 'description', 'timezone'], array_keys($body));
        self::assertSame($providerId, $body['providerId']);
        self::assertSame('Kayak en el Sella', $body['title']);
        self::assertSame('Descenso del río', $body['description']);
        self::assertSame('Europe/Madrid', $body['timezone']);
        self::assertSame(substr($location, strlen('/api/experiences/')), $body['id']);
    }

    public function testConsultaDeExperienciaInexistenteDevuelve404ExperienceNotFound(): void
    {
        $client = static::createClient();
        $unknownId = ExperienceId::generate()->value();

        $client->request('GET', '/api/experiences/'.$unknownId);

        $this->assertProblemJson($client->getResponse(), 404, 'experience-not-found');
    }

    public function testJsonRotoEnAltaDevuelve400MalformedJson(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/experiences',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{',
        );

        $this->assertProblemJson($client->getResponse(), 400, 'malformed-json');
    }

    public function testContentTypeTextPlainEnAltaDevuelve415(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/experiences',
            server: ['CONTENT_TYPE' => 'text/plain'],
            content: 'not json',
        );

        $this->assertProblemJson($client->getResponse(), 415, 'unsupported-media-type');
    }

    public function testAltaSinTitleDevuelve422InvalidValue(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/experiences',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'providerId' => ProviderId::generate()->value(),
                'description' => 'Descenso del río',
                'timezone' => 'Europe/Madrid',
            ], \JSON_THROW_ON_ERROR),
        );

        $this->assertProblemJson($client->getResponse(), 422, 'invalid-value');
    }

    public function testAltaConTituloVacioDevuelve422InvalidValue(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/experiences',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'providerId' => ProviderId::generate()->value(),
                'title' => '',
                'description' => 'Descenso del río',
                'timezone' => 'Europe/Madrid',
            ], \JSON_THROW_ON_ERROR),
        );

        $this->assertProblemJson($client->getResponse(), 422, 'invalid-value');
    }

    public function testAltaConTituloDemasiadoLargoDevuelve422InvalidValue(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/experiences',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'providerId' => ProviderId::generate()->value(),
                'title' => str_repeat('a', 151),
                'description' => 'Descenso del río',
                'timezone' => 'Europe/Madrid',
            ], \JSON_THROW_ON_ERROR),
        );

        $this->assertProblemJson($client->getResponse(), 422, 'invalid-value');
    }

    public function testAltaConTimezoneInexistenteDevuelve422InvalidValue(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/experiences',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'providerId' => ProviderId::generate()->value(),
                'title' => 'Kayak en el Sella',
                'description' => 'Descenso del río',
                'timezone' => 'Mars/Olympus',
            ], \JSON_THROW_ON_ERROR),
        );

        $this->assertProblemJson($client->getResponse(), 422, 'invalid-value');
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
