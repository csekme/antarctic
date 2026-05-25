<?php

declare(strict_types=1);

namespace Tests\Framework\Http;

use Framework\Http\HttpAdapter;
use Framework\Response as LegacyResponse;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\TestCase;

final class HttpAdapterTest extends TestCase
{
    public function testToLegacyRequestPreservesQueryStringAsUri(): void
    {
        $psrRequest = (new ServerRequest('POST', '/api/v1/login', ['Content-Type' => 'application/json']))
            ->withQueryParams(['from' => 'home'])
            ->withParsedBody(['email' => 'a@b.c'])
            ->withCookieParams(['sid' => '123'])
            ->withUploadedFiles(['avatar' => new UploadedFile('php://temp', 0, UPLOAD_ERR_OK)]);

        $psrRequest = $psrRequest->withAttribute('_serverParams', null); // no-op, just to keep test obvious

        $reflection = new \ReflectionObject($psrRequest);
        $serverParamsProp = $reflection->getProperty('serverParams');
        $serverParamsProp->setAccessible(true);
        $serverParamsProp->setValue($psrRequest, [
            'QUERY_STRING' => 'api/v1/login&from=home',
            'REQUEST_METHOD' => 'POST',
            'HTTP_X_CSRF_TOKEN' => 'csrf-value',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $legacy = HttpAdapter::toLegacyRequest($psrRequest);

        $this->assertSame('api/v1/login&from=home', $legacy->uri);
        $this->assertSame('POST', $legacy->method);
        $this->assertSame(['from' => 'home'], $legacy->get);
        $this->assertSame(['email' => 'a@b.c'], $legacy->post);
        $this->assertSame(['sid' => '123'], $legacy->cookie);
        $this->assertArrayHasKey('avatar', $legacy->files);
        $this->assertSame('csrf-value', $legacy->getCSRFFromHeader());
        $this->assertTrue($legacy->isContentTypeJson());
    }

    public function testToLegacyRequestFallsBackToUriQueryWhenServerParamMissing(): void
    {
        $psrRequest = new ServerRequest('GET', '/posts?id=42&from=feed');

        $legacy = HttpAdapter::toLegacyRequest($psrRequest);

        $this->assertSame('id=42&from=feed', $legacy->uri);
        $this->assertSame('GET', $legacy->method);
        $this->assertSame([], $legacy->post);
    }

    public function testToPsrResponseTranslatesStatusBodyAndHeaders(): void
    {
        $legacy = new LegacyResponse();
        $legacy->setStatusCode(201);
        $legacy->addHeader('Content-Type: application/json');
        $legacy->addHeader('X-Trace-Id: abc-123');
        $legacy->setBody('{"ok":true}');

        $psr = HttpAdapter::toPsrResponse($legacy);

        $this->assertSame(201, $psr->getStatusCode());
        $this->assertSame(['application/json'], $psr->getHeader('Content-Type'));
        $this->assertSame(['abc-123'], $psr->getHeader('X-Trace-Id'));
        $this->assertSame('{"ok":true}', (string) $psr->getBody());
    }

    public function testToPsrResponseDefaultsStatusTo200WhenLegacyStatusIsZero(): void
    {
        $legacy = new LegacyResponse();
        $legacy->setBody('hello');

        $psr = HttpAdapter::toPsrResponse($legacy);

        $this->assertSame(200, $psr->getStatusCode());
        $this->assertSame('hello', (string) $psr->getBody());
    }
}
