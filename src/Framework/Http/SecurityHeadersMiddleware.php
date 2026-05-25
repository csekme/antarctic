<?php

declare(strict_types=1);

namespace Framework\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Decorates every response with a baseline of security headers (HSTS, frame
 * options, content-type sniffing, referrer policy, CSP, permissions policy,
 * cross-domain policy). Headers already present on the downstream response
 * are left untouched, so endpoints can opt out of a default per response.
 *
 * `Strict-Transport-Security` is only emitted when the request is HTTPS to
 * avoid pinning clients to a scheme the server cannot serve. Behind a TLS-
 * terminating reverse proxy, enable `trust_proxy` so `X-Forwarded-Proto` is
 * honoured.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /** @var array<string, string> */
    private array $headers;
    private bool $hstsOnlyHttps;
    private bool $trustProxy;

    /**
     * @param array{
     *   headers?: array<string, string>,
     *   hsts_only_https?: bool,
     *   trust_proxy?: bool,
     * } $config
     */
    public function __construct(array $config)
    {
        $this->headers = $config['headers'] ?? [];
        $this->hstsOnlyHttps = $config['hsts_only_https'] ?? true;
        $this->trustProxy = $config['trust_proxy'] ?? false;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $isHttps = RequestScheme::isHttps($request, $this->trustProxy);

        foreach ($this->headers as $name => $value) {
            if ($value === '') {
                continue;
            }
            if (strcasecmp($name, 'Strict-Transport-Security') === 0
                && $this->hstsOnlyHttps
                && !$isHttps
            ) {
                continue;
            }
            if ($response->hasHeader($name)) {
                continue;
            }
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
