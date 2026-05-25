<?php

declare(strict_types=1);

namespace Tests\Framework\Http;

use Framework\Http\ContentNegotiation;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class ContentNegotiationTest extends TestCase
{
    public function testApiPathAlwaysWantsJson(): void
    {
        $request = new ServerRequest('GET', '/api/v1/users');

        $this->assertTrue(ContentNegotiation::wantsJson($request));
    }

    public function testApiPathInLegacyQueryString(): void
    {
        $request = (new ServerRequest('GET', '/'))
            ->withQueryParams([])
            ->withHeader('Accept', 'text/html');

        $reflection = new \ReflectionObject($request);
        $prop = $reflection->getProperty('serverParams');
        $prop->setAccessible(true);
        $prop->setValue($request, ['QUERY_STRING' => 'api/v1/users']);

        $this->assertTrue(ContentNegotiation::wantsJson($request));
    }

    public function testHtmlAcceptHeaderPrefersHtml(): void
    {
        $request = (new ServerRequest('GET', '/dashboard'))
            ->withHeader('Accept', 'text/html,application/xhtml+xml,application/json;q=0.5');

        $this->assertFalse(ContentNegotiation::wantsJson($request));
    }

    public function testJsonAcceptHeaderPrefersJson(): void
    {
        $request = (new ServerRequest('GET', '/dashboard'))
            ->withHeader('Accept', 'application/json,text/html;q=0.5');

        $this->assertTrue(ContentNegotiation::wantsJson($request));
    }

    public function testProblemJsonAcceptRecognised(): void
    {
        $request = (new ServerRequest('GET', '/dashboard'))
            ->withHeader('Accept', 'application/problem+json');

        $this->assertTrue(ContentNegotiation::wantsJson($request));
    }

    public function testEmptyAcceptHeaderFallsBackToHtml(): void
    {
        $request = new ServerRequest('GET', '/dashboard');

        $this->assertFalse(ContentNegotiation::wantsJson($request));
    }
}
