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

    /**
     * Mirror of {@see self::isHttps()} for code paths that only have a legacy
     * `$_SERVER`-shape array (e.g. controllers still running through the
     * `LegacyDispatcherMiddleware` shim). Honours the same headers as the
     * PSR-7 variant — only the input shape differs.
     *
     * @param array<string,mixed> $server
     */
    public static function isHttpsFromServerParams(array $server, bool $trustProxy): bool
    {
        $https = $server['HTTPS'] ?? '';
        if (is_string($https) && $https !== '' && strcasecmp($https, 'off') !== 0) {
            return true;
        }
        if (($server['SERVER_PORT'] ?? null) === 443 || ($server['SERVER_PORT'] ?? null) === '443') {
            return true;
        }
        if ($trustProxy) {
            $forwarded = $server['HTTP_X_FORWARDED_PROTO'] ?? '';
            if (is_string($forwarded) && strcasecmp($forwarded, 'https') === 0) {
                return true;
            }
        }
        return false;
    }

    public static function trustProxyFromEnv(): bool
    {
        return filter_var(getenv('APP_TRUST_PROXY') ?: '0', FILTER_VALIDATE_BOOL);
    }

    public static function forceHttpsFromEnv(): bool
    {
        return filter_var(getenv('APP_FORCE_HTTPS') ?: '0', FILTER_VALIDATE_BOOL);
    }
}
