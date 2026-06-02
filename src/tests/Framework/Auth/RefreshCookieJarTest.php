<?php

declare(strict_types=1);

namespace Tests\Framework\Auth;

use Framework\Auth\RefreshCookieJar;
use PHPUnit\Framework\TestCase;

final class RefreshCookieJarTest extends TestCase
{
    public function testSecureContextUsesHostPrefixAndRootPath(): void
    {
        $jar = new RefreshCookieJar(secure: true);
        $this->assertSame('__Host-refresh', $jar->refreshCookieName());
        $this->assertSame('/', $jar->refreshCookiePath());

        $header = $jar->buildRefreshSetCookie('abc', 60);
        $this->assertStringStartsWith('Set-Cookie: __Host-refresh=abc;', $header);
        $this->assertStringContainsString('Path=/;', $header);
        $this->assertStringContainsString('Secure', $header);
        $this->assertStringContainsString('HttpOnly', $header);
        $this->assertStringContainsString('SameSite=Strict', $header);
    }

    public function testDevContextDropsHostPrefixAndScopesPath(): void
    {
        $jar = new RefreshCookieJar(secure: false);
        $this->assertSame('refresh_token', $jar->refreshCookieName());
        $this->assertSame('/api/v1/auth', $jar->refreshCookiePath());

        $header = $jar->buildRefreshSetCookie('abc', 60);
        $this->assertStringStartsWith('Set-Cookie: refresh_token=abc;', $header);
        $this->assertStringContainsString('Path=/api/v1/auth;', $header);
        $this->assertStringNotContainsString('Secure', $header);
    }

    public function testCsrfCookieIsJsReadable(): void
    {
        $jar = new RefreshCookieJar(secure: true);
        $header = $jar->buildCsrfSetCookie('csrf-value', 60);

        $this->assertStringContainsString('csrf_token=csrf-value', $header);
        $this->assertStringContainsString('Path=/;', $header);
        $this->assertStringNotContainsString('HttpOnly', $header);
    }
}
