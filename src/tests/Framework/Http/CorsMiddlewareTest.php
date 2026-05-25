<?php

declare(strict_types=1);

namespace Tests\Framework\Http;

use Framework\Http\CorsMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CorsMiddlewareTest extends TestCase
{
    public function testPassesThroughWhenOriginHeaderAbsent(): void
    {
        $middleware = new CorsMiddleware(['allowed_origins' => ['https://app.example.com']]);

        $response = $middleware->process(new ServerRequest('GET', '/api/v1/ping'), $this->okHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testShortCircuitsAllowedPreflight(): void
    {
        $middleware = new CorsMiddleware([
            'allowed_origins' => ['https://app.example.com'],
            'allowed_methods' => ['GET', 'POST'],
            'allowed_headers' => ['Authorization', 'Content-Type'],
            'allow_credentials' => true,
            'max_age' => 600,
        ]);

        $request = (new ServerRequest('OPTIONS', '/api/v1/auth/login'))
            ->withHeader('Origin', 'https://app.example.com')
            ->withHeader('Access-Control-Request-Method', 'POST')
            ->withHeader('Access-Control-Request-Headers', 'Authorization');

        $handler = new class implements RequestHandlerInterface {
            public bool $called = false;
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;
                return new Response(200);
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertFalse($handler->called, 'preflight must not reach downstream handler');
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('https://app.example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('GET, POST', $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertSame('Authorization, Content-Type', $response->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertSame('600', $response->getHeaderLine('Access-Control-Max-Age'));
        $this->assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    public function testRejectsDisallowedPreflight(): void
    {
        $middleware = new CorsMiddleware(['allowed_origins' => ['https://trusted.example.com']]);

        $request = (new ServerRequest('OPTIONS', '/api/v1/anything'))
            ->withHeader('Origin', 'https://evil.example.com')
            ->withHeader('Access-Control-Request-Method', 'POST');

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testDecoratesAllowedResponse(): void
    {
        $middleware = new CorsMiddleware([
            'allowed_origins' => ['https://app.example.com'],
            'exposed_headers' => ['X-Request-Id'],
            'allow_credentials' => true,
        ]);

        $request = (new ServerRequest('GET', '/api/v1/me'))
            ->withHeader('Origin', 'https://app.example.com');

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame('https://app.example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('Origin', $response->getHeaderLine('Vary'));
        $this->assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
        $this->assertSame('X-Request-Id', $response->getHeaderLine('Access-Control-Expose-Headers'));
    }

    public function testWildcardOriginAllowsAnything(): void
    {
        $middleware = new CorsMiddleware(['allowed_origins' => ['*'], 'allow_credentials' => false]);

        $request = (new ServerRequest('GET', '/api/v1/me'))
            ->withHeader('Origin', 'https://anywhere.test');

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame('https://anywhere.test', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Credentials'));
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
