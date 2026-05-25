<?php

declare(strict_types=1);

namespace Tests\Framework\Controllers\Api\V1;

use Framework\Controllers\Api\V1\DocsController;
use Framework\OpenApi\OpenApiGenerator;
use Framework\Request;
use Framework\Response;
use PHPUnit\Framework\TestCase;

final class DocsControllerTest extends TestCase
{
    private function newController(): DocsController
    {
        return new DocsController(
            new Request('', 'GET', [], [], [], [], []),
            new Response(),
        );
    }

    public function testJsonReturnsScannedSpecAsApplicationJson(): void
    {
        $controller = $this->newController();
        $controller->setGenerator(OpenApiGenerator::forSource(dirname(__DIR__, 5)));

        $response = $controller->json();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains('Content-Type: application/json; charset=utf-8', $response->getHeaders());

        $doc = json_decode($response->getBody(), true);
        $this->assertIsArray($doc);
        $this->assertSame('Antarctic API', $doc['info']['title']);
        $this->assertArrayHasKey('/api/v1/auth/login', $doc['paths']);
    }

    public function testJsonPrefersCacheFileWhenPresent(): void
    {
        $cacheFile = sys_get_temp_dir() . '/antarctic-docs-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($cacheFile, '{"openapi":"3.0.0","info":{"title":"Cached"}}');

        $controller = $this->newController();
        $controller->setGenerator(new OpenApiGenerator([], $cacheFile));

        try {
            $body = $controller->json()->getBody();
            $this->assertSame('{"openapi":"3.0.0","info":{"title":"Cached"}}', $body);
        } finally {
            @unlink($cacheFile);
        }
    }

    public function testUiReturnsSwaggerHtmlWhenEnabled(): void
    {
        $controller = $this->newController();
        $controller->setUiEnabled(true);

        $response = $controller->ui();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains('Content-Type: text/html; charset=utf-8', $response->getHeaders());
        $body = $response->getBody();
        $this->assertStringContainsString('swagger-ui', $body);
        $this->assertStringContainsString('/api/v1/docs.json', $body);
    }

    public function testUiReturns404ProblemJsonWhenDisabled(): void
    {
        $controller = $this->newController();
        $controller->setUiEnabled(false);

        $response = $controller->ui();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertContains('Content-Type: application/problem+json; charset=utf-8', $response->getHeaders());
        $payload = json_decode($response->getBody(), true);
        $this->assertSame(404, $payload['status']);
    }
}
