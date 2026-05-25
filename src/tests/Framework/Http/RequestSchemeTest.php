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
}
