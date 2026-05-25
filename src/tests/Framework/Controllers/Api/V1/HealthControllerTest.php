<?php

declare(strict_types=1);

namespace Tests\Framework\Controllers\Api\V1;

use Framework\Controllers\Api\V1\HealthController;
use PDO;
use PHPUnit\Framework\TestCase;

final class HealthControllerTest extends TestCase
{
    public function testHealthzAlwaysReturnsOk(): void
    {
        $controller = new HealthController([]);

        $response = $controller->liveness();

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('ok', $body['status']);
        $this->assertHeaderContains('Cache-Control: no-store', $response->getHeaders());
    }

    public function testReadyzReturns200WhenDbPingSucceeds(): void
    {
        $controller = new HealthController([]);
        $pdo = new PDO('sqlite::memory:');
        $controller->setPdoFactory(static fn (): PDO => $pdo);

        $response = $controller->readiness();

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('ready', $body['status']);
        $this->assertSame('ok', $body['checks']['database']);
    }

    public function testReadyzReturns503WhenDbPingFails(): void
    {
        $controller = new HealthController([]);
        $controller->setPdoFactory(static function (): PDO {
            throw new \RuntimeException('connection refused');
        });

        $response = $controller->readiness();

        $this->assertSame(503, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('Service Unavailable', $body['title']);
        $this->assertSame(503, $body['status']);
        $this->assertSame('fail', $body['checks']['database']);
        $this->assertHeaderContains('Content-Type: application/problem+json; charset=utf-8', $response->getHeaders());
        $this->assertHeaderContains('Cache-Control: no-store', $response->getHeaders());
    }

    /**
     * @param list<string> $headers
     */
    private function assertHeaderContains(string $expected, array $headers): void
    {
        $this->assertContains($expected, $headers, "Expected header '{$expected}' to be present");
    }
}
