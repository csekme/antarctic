<?php

declare(strict_types=1);

namespace Tests\Framework\Http;

use Framework\Http\TraceIdMiddleware;
use Framework\Logging\TraceIdHolder;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class TraceIdMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        TraceIdHolder::clear();
    }

    public function testGeneratesIdWhenHeaderMissing(): void
    {
        $middleware = new TraceIdMiddleware();
        $capturedAttribute = null;

        $handler = new class ($capturedAttribute) implements RequestHandlerInterface {
            public function __construct(private mixed &$captured)
            {
            }
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = $request->getAttribute(TraceIdMiddleware::ATTRIBUTE);
                return new Response(200);
            }
        };

        $response = $middleware->process(new ServerRequest('GET', '/api/v1/ping'), $handler);

        $id = $response->getHeaderLine(TraceIdMiddleware::HEADER);
        $this->assertNotSame('', $id);
        $this->assertSame($id, $capturedAttribute);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $id);
    }

    public function testHonoursValidInboundHeader(): void
    {
        $middleware = new TraceIdMiddleware();

        $request = (new ServerRequest('GET', '/api/v1/me'))
            ->withHeader(TraceIdMiddleware::HEADER, 'edge-abc-123');

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame('edge-abc-123', $response->getHeaderLine(TraceIdMiddleware::HEADER));
    }

    public function testRejectsInvalidInboundHeader(): void
    {
        $middleware = new TraceIdMiddleware();

        // Values PSR-7 accepts but our whitelist rejects: spaces, semicolons,
        // quotes. The middleware must generate a fresh id instead of echoing
        // them, blocking log-injection via the trace channel.
        $request = (new ServerRequest('GET', '/api/v1/me'))
            ->withHeader(TraceIdMiddleware::HEADER, 'foo bar; injected="yes"');

        $response = $middleware->process($request, $this->okHandler());

        $emitted = $response->getHeaderLine(TraceIdMiddleware::HEADER);
        $this->assertNotSame('foo bar; injected="yes"', $emitted);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $emitted);
    }

    public function testDoesNotOverrideDownstreamResponseHeader(): void
    {
        $middleware = new TraceIdMiddleware();

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new Response(200))->withHeader(TraceIdMiddleware::HEADER, 'downstream');
            }
        };

        $response = $middleware->process(new ServerRequest('GET', '/'), $handler);

        $this->assertSame('downstream', $response->getHeaderLine(TraceIdMiddleware::HEADER));
    }

    public function testPopulatesHolderDuringHandlerAndClearsAfter(): void
    {
        $middleware = new TraceIdMiddleware();
        $duringHandler = null;

        $handler = new class ($duringHandler) implements RequestHandlerInterface {
            public function __construct(private mixed &$captured)
            {
            }
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = TraceIdHolder::get();
                return new Response(200);
            }
        };

        $middleware->process(new ServerRequest('GET', '/'), $handler);

        $this->assertNotNull($duringHandler);
        $this->assertNull(TraceIdHolder::get(), 'holder must reset after the request');
    }

    public function testHolderClearedEvenOnException(): void
    {
        $middleware = new TraceIdMiddleware();

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('boom');
            }
        };

        try {
            $middleware->process(new ServerRequest('GET', '/'), $handler);
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
            $this->assertNull(TraceIdHolder::get());
        }
    }

    private function okHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };
    }
}
