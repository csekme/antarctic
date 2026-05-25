<?php

declare(strict_types=1);

namespace Framework\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CORS middleware with allow-list. Short-circuits OPTIONS preflight; on
 * normal requests it delegates to the handler and decorates the response.
 *
 * Same-origin requests (no Origin header) pass through untouched.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private array $allowedOrigins;
    /** @var list<string> */
    private array $allowedMethods;
    /** @var list<string> */
    private array $allowedHeaders;
    /** @var list<string> */
    private array $exposedHeaders;
    private bool $allowCredentials;
    private int $maxAge;

    /**
     * @param array{
     *   allowed_origins?: list<string>,
     *   allowed_methods?: list<string>,
     *   allowed_headers?: list<string>,
     *   exposed_headers?: list<string>,
     *   allow_credentials?: bool,
     *   max_age?: int,
     * } $config
     */
    public function __construct(array $config)
    {
        $this->allowedOrigins = $config['allowed_origins'] ?? [];
        $this->allowedMethods = $config['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $this->allowedHeaders = $config['allowed_headers'] ?? ['Authorization', 'Content-Type'];
        $this->exposedHeaders = $config['exposed_headers'] ?? [];
        $this->allowCredentials = $config['allow_credentials'] ?? false;
        $this->maxAge = $config['max_age'] ?? 600;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        if ($origin === '') {
            return $handler->handle($request);
        }

        if (!$this->originAllowed($origin)) {
            if (strtoupper($request->getMethod()) === 'OPTIONS') {
                return new Response(403);
            }
            return $handler->handle($request);
        }

        if (strtoupper($request->getMethod()) === 'OPTIONS'
            && $request->hasHeader('Access-Control-Request-Method')
        ) {
            return $this->decoratePreflight(new Response(204), $origin);
        }

        return $this->decorate($handler->handle($request), $origin);
    }

    private function originAllowed(string $origin): bool
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            return true;
        }
        return in_array($origin, $this->allowedOrigins, true);
    }

    private function decorate(ResponseInterface $response, string $origin): ResponseInterface
    {
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withAddedHeader('Vary', 'Origin');

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        if ($this->exposedHeaders !== []) {
            $response = $response->withHeader(
                'Access-Control-Expose-Headers',
                implode(', ', $this->exposedHeaders),
            );
        }

        return $response;
    }

    private function decoratePreflight(ResponseInterface $response, string $origin): ResponseInterface
    {
        return $this->decorate($response, $origin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
    }
}
