<?php

declare(strict_types=1);

namespace Tests\Framework\Http;

use Framework\Http\MiddlewarePipeline;
use Framework\Http\NotFoundHandler;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewarePipelineTest extends TestCase
{
    public function testFallsBackToHandlerWhenPipelineEmpty(): void
    {
        $pipeline = new MiddlewarePipeline([], new NotFoundHandler());

        $response = $pipeline->handle(new ServerRequest('GET', '/missing'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('not_found', (string) $response->getBody());
    }

    public function testWalksMiddlewaresInOrderAndPassesRequest(): void
    {
        $log = [];

        $first = $this->middleware(
            function (ServerRequestInterface $req, RequestHandlerInterface $next) use (&$log) {
                $log[] = 'first:before';
                $response = $next->handle($req->withAttribute('hop', ($req->getAttribute('hop') ?? 0) + 1));
                $log[] = 'first:after';
                return $response;
            }
        );

        $second = $this->middleware(
            function (ServerRequestInterface $req, RequestHandlerInterface $next) use (&$log) {
                $log[] = 'second:before';
                $response = $next->handle($req);
                $log[] = 'second:after';
                return $response->withHeader('X-Hop', (string) $req->getAttribute('hop'));
            }
        );

        $terminal = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'ok');
            }
        };

        $pipeline = new MiddlewarePipeline([$first, $second], $terminal);
        $response = $pipeline->handle(new ServerRequest('GET', '/ping'));

        $this->assertSame(['first:before', 'second:before', 'second:after', 'first:after'], $log);
        $this->assertSame(['1'], $response->getHeader('X-Hop'));
    }

    public function testReusableAcrossSubRequests(): void
    {
        $counter = 0;
        $stateful = $this->middleware(
            function (ServerRequestInterface $req, RequestHandlerInterface $next) use (&$counter) {
                $counter++;
                return $next->handle($req);
            }
        );

        $terminal = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], '');
            }
        };

        $pipeline = new MiddlewarePipeline([$stateful], $terminal);

        $pipeline->handle(new ServerRequest('GET', '/one'));
        $pipeline->handle(new ServerRequest('GET', '/two'));

        $this->assertSame(2, $counter, 'pipeline must be safe to invoke multiple times');
    }

    /**
     * @param callable(ServerRequestInterface, RequestHandlerInterface): ResponseInterface $fn
     */
    private function middleware(callable $fn): MiddlewareInterface
    {
        return new class($fn) implements MiddlewareInterface {
            public function __construct(private $fn)
            {
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return ($this->fn)($request, $handler);
            }
        };
    }
}
