<?php

declare(strict_types=1);

namespace App\Tests\Shared\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ProblemJsonTest extends WebTestCase
{
    public function testUnaConflictProduce409ConCuerpoProblemJson(): void
    {
        $response = $this->postJson('{"exception":"conflict"}');

        $this->assertProblemJson($response, 409, 'example-conflict');
    }

    public function testUnaHijaDeNotFoundProduce404ConSuPropioType(): void
    {
        $response = $this->postJson('{"exception":"not-found"}');

        $this->assertProblemJson($response, 404, 'example-not-found');
    }

    public function testJsonRotoProduce400MalformedJson(): void
    {
        $response = $this->postJson('{');

        $this->assertProblemJson($response, 400, 'malformed-json');
    }

    public function testContentTypeTextPlainProduce415UnsupportedMediaType(): void
    {
        $response = $this->postJson('not json', 'text/plain');

        $this->assertProblemJson($response, 415, 'unsupported-media-type');
    }

    public function testRuntimeExceptionProduce500SinMensajeInterno(): void
    {
        $response = $this->postJson('{"exception":"runtime"}');
        $data = $this->assertProblemJson($response, 500, 'about:blank');

        self::assertSame('Internal server error', $data['detail']);
        self::assertStringNotContainsString('secret-internal-message', (string) $response->getContent());
    }

    public function testInvalidValueProduce422ConTypeInvalidValue(): void
    {
        $response = $this->postJson('{"exception":"invalid-value"}');

        $this->assertProblemJson($response, 422, 'invalid-value');
    }

    private function postJson(string $body, string $contentType = 'application/json'): Response
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/_test/problem-json',
            [],
            [],
            ['CONTENT_TYPE' => $contentType],
            $body,
        );

        return $client->getResponse();
    }

    /**
     * @return array{type: string, title: string, status: int, detail: string}
     */
    private function assertProblemJson(Response $response, int $status, string $type): array
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

        return $data;
    }
}
