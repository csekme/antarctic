<?php

declare(strict_types=1);

namespace Tests\Framework\OpenApi;

use Framework\OpenApi\OpenApiGenerator;
use PHPUnit\Framework\TestCase;

final class OpenApiGeneratorTest extends TestCase
{
    private string $sourceRoot;

    protected function setUp(): void
    {
        $this->sourceRoot = dirname(__DIR__, 3);
    }

    public function testScanProducesOpenApi3Document(): void
    {
        $generator = OpenApiGenerator::forSource($this->sourceRoot);

        $json = $generator->scan();
        $doc = json_decode($json, true);

        $this->assertIsArray($doc);
        $this->assertSame('3.0.0', $doc['openapi']);
        $this->assertSame('Antarctic API', $doc['info']['title']);
    }

    public function testScanIncludesLoginPath(): void
    {
        $generator = OpenApiGenerator::forSource($this->sourceRoot);

        $doc = json_decode($generator->scan(), true);

        $this->assertArrayHasKey('paths', $doc);
        $this->assertArrayHasKey('/api/v1/auth/login', $doc['paths']);
        $this->assertArrayHasKey('post', $doc['paths']['/api/v1/auth/login']);
    }

    public function testScanIncludesLoginRequestSchema(): void
    {
        $generator = OpenApiGenerator::forSource($this->sourceRoot);

        $doc = json_decode($generator->scan(), true);

        $this->assertArrayHasKey('components', $doc);
        $this->assertArrayHasKey('schemas', $doc['components']);
        $this->assertArrayHasKey('LoginRequest', $doc['components']['schemas']);
        $this->assertContains('email', $doc['components']['schemas']['LoginRequest']['required']);
        $this->assertContains('password', $doc['components']['schemas']['LoginRequest']['required']);
    }

    public function testScanIncludesBearerSecurityScheme(): void
    {
        $generator = OpenApiGenerator::forSource($this->sourceRoot);

        $doc = json_decode($generator->scan(), true);

        $this->assertArrayHasKey('securitySchemes', $doc['components']);
        $this->assertSame('http', $doc['components']['securitySchemes']['bearerAuth']['type']);
        $this->assertSame('bearer', $doc['components']['securitySchemes']['bearerAuth']['scheme']);
        $this->assertSame('JWT', $doc['components']['securitySchemes']['bearerAuth']['bearerFormat']);
    }

    public function testCachedFileTakesPrecedenceOverScan(): void
    {
        $cacheFile = sys_get_temp_dir() . '/antarctic-openapi-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($cacheFile, '{"openapi":"3.0.0","info":{"title":"From Cache"}}');

        $generator = new OpenApiGenerator([], $cacheFile);
        try {
            $json = $generator->toJson();
            $this->assertSame('{"openapi":"3.0.0","info":{"title":"From Cache"}}', $json);
        } finally {
            @unlink($cacheFile);
        }
    }

    public function testEmptyScanPathsYieldsMinimalDocument(): void
    {
        $generator = new OpenApiGenerator([]);
        $this->assertSame('{"openapi":"3.0.0"}', $generator->scan());
    }
}
