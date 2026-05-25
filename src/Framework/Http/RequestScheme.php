<?php

declare(strict_types=1);

namespace Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Centralised "is this request HTTPS?" decision. Behind a TLS-terminating
 * reverse proxy the TCP request reaching PHP is plain HTTP, so we must look
 * at `X-Forwarded-Proto` to recover the user's actual scheme. Trust is
 * opt-in via `APP_TRUST_PROXY`; without it a client could spoof the header.
 *
 * Shared by {@see SecurityHeadersMiddleware}, {@see HttpsRedirectMiddleware}
 * and any future code that needs scheme-aware behaviour.
 */
final class RequestScheme
{
    public static function isHttps(ServerRequestInterface $request, bool $trustProxy): bool
    {
        if (strtolower($request->getUri()->getScheme()) === 'https') {
            return true;
        }
        if ($trustProxy
            && strcasecmp($request->getHeaderLine('X-Forwarded-Proto'), 'https') === 0
        ) {
            return true;
        }
        return false;
    }

    public static function trustProxyFromEnv(): bool
    {
        return filter_var(getenv('APP_TRUST_PROXY') ?: '0', FILTER_VALIDATE_BOOL);
    }
}
