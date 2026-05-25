<?php

declare(strict_types=1);

namespace Framework\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Optional middleware that 301-redirects plain HTTP requests to the HTTPS
 * variant. Disabled by default; enable in production with `APP_FORCE_HTTPS=1`.
 *
 *   - `OPTIONS` requests pass through (CORS preflight handled separately).
 *   - Behind a TLS-terminating proxy, set `APP_TRUST_PROXY=1` so the
 *     middleware honours `X-Forwarded-Proto` and does not redirect-loop.
 *   - Excluded path prefixes (default: none) bypass the redirect. Useful for
 *     loopback healthchecks (`/api/v1/healthz`, `/api/v1/readyz`) where the
 *     k8s probe targets the pod IP on plain HTTP.
 */
final class HttpsRedirectMiddleware implements MiddlewareInterface
{
    private bool $trustProxy;
    /** @var list<string> */
    private array $excludedPrefixes;

    /**
     * @param list<string> $excludedPrefixes
     */
    public function __construct(bool $trustProxy = false, array $excludedPrefixes = [])
    {
        $this->trustProxy = $trustProxy;
        $this->excludedPrefixes = $excludedPrefixes;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $handler->handle($request);
        }

        if (RequestScheme::isHttps($request, $this->trustProxy)) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        foreach ($this->excludedPrefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                return $handler->handle($request);
            }
        }

        $target = $request->getUri()->withScheme('https');
        return new Response(301, ['Location' => (string) $target]);
    }
}
