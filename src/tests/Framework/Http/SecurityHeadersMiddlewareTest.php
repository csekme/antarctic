<?php

declare(strict_types=1);

namespace Tests\Framework\Http;

use Framework\Http\SecurityHeadersMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    public function testAppliesAllConfiguredHeadersOnHttps(): void
    {
        $middleware = new SecurityHeadersMiddleware([
            'headers' => [
                'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
                'Content-Security-Policy' => "default-src 'self'",
            ],
        ]);

        $response = $middleware->process(
            new ServerRequest('GET', 'https://api.example.com/api/v1/ping'),
            $this->okHandler(),
        );

        $this->assertSame('max-age=31536000; includeSubDomains', $response->getHeaderLine('Strict-Transport-Security'));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->getHeaderLine('Referrer-Policy'));
        $this->assertSame("default-src 'self'", $response->getHeaderLine('Content-Security-Policy'));
    }

    public function testSkipsHstsOnPlainHttp(): void
    {
        $middleware = new SecurityHeadersMiddleware([
            'headers' => [
                'Strict-Transport-Security' => 'max-age=31536000',
                'X-Frame-Options' => 'DENY',
            ],
        ]);

        $response = $middleware->process(
            new ServerRequest('GET', 'http://api.example.com/api/v1/ping'),
            $this->okHandler(),
        );

        $this->assertFalse($response->hasHeader('Strict-Transport-Security'));
        $this->assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
    }

    public function testHonoursForwardedProtoWhenTrustProxy(): void
    {
        $middleware = new SecurityHeadersMiddleware([
            'headers' => ['Strict-Transport-Security' => 'max-age=31536000'],
            'trust_proxy' => true,
        ]);

        $request = (new ServerRequest('GET', 'http://api.example.com/api/v1/ping'))
            ->withHeader('X-Forwarded-Proto', 'https');

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame('max-age=31536000', $response->getHeaderLine('Strict-Transport-Security'));
    }

    public function testIgnoresForwardedProtoWhenTrustProxyOff(): void
    {
        $middleware = new SecurityHeadersMiddleware([
            'headers' => ['Strict-Transport-Security' => 'max-age=31536000'],
            'trust_proxy' => false,
        ]);

        $request = (new ServerRequest('GET', 'http://api.example.com/api/v1/ping'))
            ->withHeader('X-Forwarded-Proto', 'https');

        $response = $middleware->process($request, $this->okHandler());

        $this->assertFalse($response->hasHeader('Strict-Transport-Security'));
    }

    public function testDoesNotOverrideExistingHeader(): void
    {
        $middleware = new SecurityHeadersMiddleware([
            'headers' => [
                'X-Frame-Options' => 'DENY',
                'Content-Security-Policy' => "default-src 'self'",
            ],
        ]);

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new Response(200))->withHeader('X-Frame-Options', 'SAMEORIGIN');
            }
        };

        $response = $middleware->process(
            new ServerRequest('GET', 'https://api.example.com/'),
            $handler,
        );

        $this->assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
        $this->assertSame("default-src 'self'", $response->getHeaderLine('Content-Security-Policy'));
    }

    public function testEmptyValueSkipsHeader(): void
    {
        $middleware = new SecurityHeadersMiddleware([
            'headers' => [
                'Content-Security-Policy' => '',
                'X-Frame-Options' => 'DENY',
            ],
        ]);

        $response = $middleware->process(
            new ServerRequest('GET', 'https://api.example.com/'),
            $this->okHandler(),
        );

        $this->assertFalse($response->hasHeader('Content-Security-Policy'));
        $this->assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
    }

    public function testHstsOnlyHttpsCanBeDisabled(): void
    {
        $middleware = new SecurityHeadersMiddleware([
            'headers' => ['Strict-Transport-Security' => 'max-age=31536000'],
            'hsts_only_https' => false,
        ]);

        $response = $middleware->process(
            new ServerRequest('GET', 'http://api.example.com/'),
            $this->okHandler(),
        );

        $this->assertSame('max-age=31536000', $response->getHeaderLine('Strict-Transport-Security'));
    }

    private function okHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'ok');
            }
        };
    }
}
