<?php

declare(strict_types=1);

namespace Tests\Framework\Http;

use Framework\Http\HttpsRedirectMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpsRedirectMiddlewareTest extends TestCase
{
    public function testRedirectsPlainHttpTo301Https(): void
    {
        $middleware = new HttpsRedirectMiddleware();

        $response = $middleware->process(
            new ServerRequest('GET', 'http://api.example.com/api/v1/me?foo=bar'),
            $this->failHandler(),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('https://api.example.com/api/v1/me?foo=bar', $response->getHeaderLine('Location'));
    }

    public function testPassesThroughHttpsRequests(): void
    {
        $middleware = new HttpsRedirectMiddleware();

        $response = $middleware->process(
            new ServerRequest('GET', 'https://api.example.com/api/v1/me'),
            $this->okHandler(),
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPassesThroughOptionsPreflight(): void
    {
        $middleware = new HttpsRedirectMiddleware();

        $response = $middleware->process(
            new ServerRequest('OPTIONS', 'http://api.example.com/api/v1/me'),
            $this->okHandler(),
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHonoursTrustedForwardedProtoHttps(): void
    {
        $middleware = new HttpsRedirectMiddleware(trustProxy: true);

        $request = (new ServerRequest('GET', 'http://api.example.com/api/v1/me'))
            ->withHeader('X-Forwarded-Proto', 'https');

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testIgnoresForwardedProtoWhenTrustOff(): void
    {
        $middleware = new HttpsRedirectMiddleware(trustProxy: false);

        $request = (new ServerRequest('GET', 'http://api.example.com/api/v1/me'))
            ->withHeader('X-Forwarded-Proto', 'https');

        $response = $middleware->process($request, $this->failHandler());

        $this->assertSame(301, $response->getStatusCode());
    }

    public function testExcludedPrefixBypassesRedirect(): void
    {
        $middleware = new HttpsRedirectMiddleware(
            trustProxy: false,
            excludedPrefixes: ['/api/v1/healthz', '/api/v1/readyz'],
        );

        $response = $middleware->process(
            new ServerRequest('GET', 'http://api.example.com/api/v1/healthz'),
            $this->okHandler(),
        );

        $this->assertSame(200, $response->getStatusCode());
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

    private function failHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('handler must not be called when redirecting');
            }
        };
    }
}
