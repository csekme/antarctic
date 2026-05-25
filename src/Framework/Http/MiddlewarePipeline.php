<?php

declare(strict_types=1);

namespace Framework\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware pipeline. Walks the middleware array, delegating to a
 * fallback handler when exhausted. Cloned on each step so the same pipeline
 * instance is safe to reuse for sub-requests.
 */
final class MiddlewarePipeline implements RequestHandlerInterface
{
    /** @var MiddlewareInterface[] */
    private array $middlewares;

    private int $index = 0;

    private RequestHandlerInterface $fallback;

    /**
     * @param MiddlewareInterface[] $middlewares
     */
    public function __construct(array $middlewares, RequestHandlerInterface $fallback)
    {
        $this->middlewares = array_values($middlewares);
        $this->fallback = $fallback;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->index >= count($this->middlewares)) {
            return $this->fallback->handle($request);
        }

        $middleware = $this->middlewares[$this->index];
        $next = clone $this;
        $next->index = $this->index + 1;

        return $middleware->process($request, $next);
    }
}
