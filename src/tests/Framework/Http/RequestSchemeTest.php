<?php

declare(strict_types=1);

namespace Tests\Framework\Http;

use Framework\Http\RequestScheme;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class RequestSchemeTest extends TestCase
{
    public function testDirectHttpsScheme(): void
    {
        $request = new ServerRequest('GET', 'https://api.example.com/');
        $this->assertTrue(RequestScheme::isHttps($request, false));
        $this->assertTrue(RequestScheme::isHttps($request, true));
    }

    public function testHttpWithoutProxyIsPlain(): void
    {
        $request = new ServerRequest('GET', 'http://api.example.com/');
        $this->assertFalse(RequestScheme::isHttps($request, false));
        $this->assertFalse(RequestScheme::isHttps($request, true));
    }

    public function testForwardedProtoHonouredOnlyWhenTrusted(): void
    {
        $request = (new ServerRequest('GET', 'http://api.example.com/'))
            ->withHeader('X-Forwarded-Proto', 'https');

        $this->assertFalse(RequestScheme::isHttps($request, false));
        $this->assertTrue(RequestScheme::isHttps($request, true));
    }

    public function testCaseInsensitiveForwardedProto(): void
    {
        $request = (new ServerRequest('GET', 'http://api.example.com/'))
            ->withHeader('X-Forwarded-Proto', 'HTTPS');

        $this->assertTrue(RequestScheme::isHttps($request, true));
    }

    public function testTrustProxyFromEnv(): void
    {
        putenv('APP_TRUST_PROXY=1');
        try {
            $this->assertTrue(RequestScheme::trustProxyFromEnv());
        } finally {
            putenv('APP_TRUST_PROXY');
        }

        putenv('APP_TRUST_PROXY=0');
        try {
            $this->assertFalse(RequestScheme::trustProxyFromEnv());
        } finally {
            putenv('APP_TRUST_PROXY');
        }
    }

    public function testForceHttpsFromEnv(): void
    {
        putenv('APP_FORCE_HTTPS=1');
        try {
            $this->assertTrue(RequestScheme::forceHttpsFromEnv());
        } finally {
            putenv('APP_FORCE_HTTPS');
        }
    }

    public function testIsHttpsFromServerParamsHonoursHttpsKey(): void
    {
        $this->assertTrue(RequestScheme::isHttpsFromServerParams(['HTTPS' => 'on'], false));
        $this->assertTrue(RequestScheme::isHttpsFromServerParams(['HTTPS' => '1'], false));
        $this->assertFalse(RequestScheme::isHttpsFromServerParams(['HTTPS' => 'off'], false));
        $this->assertFalse(RequestScheme::isHttpsFromServerParams(['HTTPS' => ''], false));
        $this->assertFalse(RequestScheme::isHttpsFromServerParams([], false));
    }

    public function testIsHttpsFromServerParamsHonoursPort443(): void
    {
        $this->assertTrue(RequestScheme::isHttpsFromServerParams(['SERVER_PORT' => 443], false));
        $this->assertTrue(RequestScheme::isHttpsFromServerParams(['SERVER_PORT' => '443'], false));
        $this->assertFalse(RequestScheme::isHttpsFromServerParams(['SERVER_PORT' => 80], false));
    }

    public function testIsHttpsFromServerParamsForwardedProtoOnlyWhenTrusted(): void
    {
        $server = ['HTTP_X_FORWARDED_PROTO' => 'https'];
        $this->assertFalse(RequestScheme::isHttpsFromServerParams($server, false));
        $this->assertTrue(RequestScheme::isHttpsFromServerParams($server, true));
    }

    public function testIsHttpsFromServerParamsForwardedProtoCaseInsensitive(): void
    {
        $this->assertTrue(RequestScheme::isHttpsFromServerParams(
            ['HTTP_X_FORWARDED_PROTO' => 'HTTPS'],
            true,
        ));
    }
}
