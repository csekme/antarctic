<?php

declare(strict_types=1);

namespace Tests\Framework\Http;

use Exception;
use Framework\Http\ErrorHandlerMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ErrorHandlerMiddlewareTest extends TestCase
{
    public function testPassesThroughWhenNoException(): void
    {
        $middleware = new ErrorHandlerMiddleware();

        $response = $middleware->process(new ServerRequest('GET', '/api/v1/ok'), $this->handlerReturning(
            new Response(200, [], 'fine'),
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('fine', (string) $response->getBody());
    }

    public function testRendersProblemJsonForApiPathRegardlessOfAcceptHeader(): void
    {
        $middleware = new ErrorHandlerMiddleware();

        $response = $middleware->process(
            new ServerRequest('GET', '/api/v1/users/9999'),
            $this->handlerThrowing(new Exception('User not found', 404)),
        );

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(404, $body['status']);
        $this->assertSame('Not Found', $body['title']);
        $this->assertSame('User not found', $body['detail']);
        $this->assertSame('about:blank', $body['type']);
    }

    public function testRendersProblemJsonForJsonAcceptHeader(): void
    {
        $middleware = new ErrorHandlerMiddleware();

        $request = (new ServerRequest('GET', '/dashboard'))
            ->withHeader('Accept', 'application/json');

        $response = $middleware->process(
            $request,
            $this->handlerThrowing(new Exception('Validation failed', 422)),
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringStartsWith('application/problem+json', $response->getHeaderLine('Content-Type'));
    }

    public function testRendersHtmlForBrowserClients(): void
    {
        $middleware = new ErrorHandlerMiddleware();

        $request = (new ServerRequest('GET', '/profile'))
            ->withHeader('Accept', 'text/html,application/xhtml+xml');

        $response = $middleware->process(
            $request,
            $this->handlerThrowing(new Exception('Profile missing', 404)),
        );

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('404 Not Found', (string) $response->getBody());
    }

    public function testMapsUnknownCodesToFiveHundredAndHidesMessage(): void
    {
        $middleware = new ErrorHandlerMiddleware();

        $request = (new ServerRequest('GET', '/api/v1/boom'));
        $response = $middleware->process(
            $request,
            $this->handlerThrowing(new \RuntimeException('SQL exploded with credentials inside')),
        );

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(500, $body['status']);
        $this->assertSame('Internal server error.', $body['detail'], 'must not leak exception text in prod');
        $this->assertArrayNotHasKey('exception', $body);
    }

    public function testDebugModeIncludesExceptionDetails(): void
    {
        $middleware = new ErrorHandlerMiddleware(debug: true);

        $request = new ServerRequest('GET', '/api/v1/boom');
        $response = $middleware->process(
            $request,
            $this->handlerThrowing(new \RuntimeException('debug-message-here')),
        );

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('debug-message-here', $body['detail']);
        $this->assertSame(\RuntimeException::class, $body['exception']);
        $this->assertArrayHasKey('file', $body);
        $this->assertArrayHasKey('line', $body);
    }

    private function handlerReturning(ResponseInterface $response): RequestHandlerInterface
    {
        return new class($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    private function handlerThrowing(\Throwable $exception): RequestHandlerInterface
    {
        return new class($exception) implements RequestHandlerInterface {
            public function __construct(private readonly \Throwable $exception) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->exception;
            }
        };
    }
}
