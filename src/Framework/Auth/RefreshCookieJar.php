<?php

declare(strict_types=1);

namespace Framework\Auth;

use Framework\Http\RequestScheme;

/**
 * Builds Set-Cookie header *strings* for the refresh and CSRF cookies. Does
 * not touch the Response — the controller takes the strings and queues them
 * via Response::addHeader(). This keeps the jar HTTP-mentes (pure value-out).
 *
 * The cookie name + path is decided per-instance based on the request scheme:
 *
 * - HTTPS (Secure context): `__Host-refresh` + Path=/ (the RFC requires Secure
 *   AND Path=/ for the `__Host-` prefix, otherwise the browser silently drops
 *   the Set-Cookie).
 * - HTTP (dev): `refresh_token` + Path=/api/v1/auth (the prefix is illegal
 *   without Secure, so we drop it and narrow the path to the auth endpoints
 *   instead — a smaller scope CSRF-wise).
 *
 * The CSRF cookie is always `csrf_token` + Path=/ (it is JS-readable so the
 * SPA can echo it back in the X-CSRF-Token header on /refresh).
 */
final class RefreshCookieJar
{
    public const REFRESH_COOKIE_SECURE_NAME = '__Host-refresh';
    public const REFRESH_COOKIE_DEV_NAME = 'refresh_token';
    public const REFRESH_COOKIE_SECURE_PATH = '/';
    public const REFRESH_COOKIE_DEV_PATH = '/api/v1/auth';
    public const CSRF_COOKIE = 'csrf_token';

    public function __construct(
        public readonly bool $secure,
    ) {
    }

    /**
     * Decide the secure context from the request server params + env.
     * Mirrors AuthController::isSecureContext().
     *
     * @param array<string, mixed> $serverParams
     */
    public static function fromServerParams(array $serverParams): self
    {
        $secure = RequestScheme::forceHttpsFromEnv()
            || RequestScheme::isHttpsFromServerParams($serverParams, RequestScheme::trustProxyFromEnv());
        return new self($secure);
    }

    public function refreshCookieName(): string
    {
        return $this->secure ? self::REFRESH_COOKIE_SECURE_NAME : self::REFRESH_COOKIE_DEV_NAME;
    }

    public function refreshCookiePath(): string
    {
        return $this->secure ? self::REFRESH_COOKIE_SECURE_PATH : self::REFRESH_COOKIE_DEV_PATH;
    }

    public function buildRefreshSetCookie(string $token, int $maxAge): string
    {
        return $this->buildCookie(
            name: $this->refreshCookieName(),
            value: $token,
            maxAge: $maxAge,
            path: $this->refreshCookiePath(),
            httpOnly: true,
            sameSite: 'Strict',
        );
    }

    public function buildRefreshClearCookie(): string
    {
        return $this->buildCookie(
            name: $this->refreshCookieName(),
            value: '',
            maxAge: 0,
            path: $this->refreshCookiePath(),
            httpOnly: true,
            sameSite: 'Strict',
        );
    }

    public function buildCsrfSetCookie(string $token, int $maxAge): string
    {
        return $this->buildCookie(
            name: self::CSRF_COOKIE,
            value: $token,
            maxAge: $maxAge,
            path: '/',
            httpOnly: false,
            sameSite: 'Strict',
        );
    }

    public function buildCsrfClearCookie(): string
    {
        return $this->buildCookie(
            name: self::CSRF_COOKIE,
            value: '',
            maxAge: 0,
            path: '/',
            httpOnly: false,
            sameSite: 'Strict',
        );
    }

    public static function generateCsrfToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function buildCookie(
        string $name,
        string $value,
        int $maxAge,
        string $path,
        bool $httpOnly,
        string $sameSite,
    ): string {
        $parts = [
            sprintf('%s=%s', $name, $value),
            sprintf('Max-Age=%d', $maxAge),
            sprintf('Path=%s', $path),
            sprintf('SameSite=%s', $sameSite),
        ];
        if ($this->secure) {
            $parts[] = 'Secure';
        }
        if ($httpOnly) {
            $parts[] = 'HttpOnly';
        }
        return 'Set-Cookie: ' . implode('; ', $parts);
    }
}
