<?php

declare(strict_types=1);

namespace Tests\Framework\Http\RateLimit;

use DateTimeImmutable;
use DateTimeZone;
use Framework\Http\RateLimit\InMemoryStore;
use Framework\Http\RateLimit\RateLimitMiddleware;
use Framework\Http\RateLimit\RateLimitRule;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimitMiddlewareTest extends TestCase
{
    public function testPassesThroughWhenNoRuleMatches(): void
    {
        $middleware = new RateLimitMiddleware(
            rules: [new RateLimitRule('/api/v1/auth/login', 5, 60)],
            store: new InMemoryStore(),
            clock: $this->frozenClock(1_000),
        );

        $response = $middleware->process(
            $this->request('/something-else', '203.0.113.1'),
            $this->ok(),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('X-RateLimit-Limit'));
    }

    public function testAllowsRequestsUnderLimitAndAttachesHeaders(): void
    {
        $middleware = new RateLimitMiddleware(
            rules: [new RateLimitRule('/api/v1/auth/login', 5, 60)],
            store: new InMemoryStore(),
            clock: $this->frozenClock(1_000),
        );

        $response = $middleware->process(
            $this->request('/api/v1/auth/login', '203.0.113.1'),
            $this->ok(),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('5', $response->getHeaderLine('X-RateLimit-Limit'));
        $this->assertSame('4', $response->getHeaderLine('X-RateLimit-Remaining'));
        $this->assertSame('1060', $response->getHeaderLine('X-RateLimit-Reset'));
    }

    public function testBlocksRequestAfterLimitWith429ProblemJson(): void
    {
        $store = new InMemoryStore();
        $middleware = new RateLimitMiddleware(
            rules: [new RateLimitRule('/api/v1/auth/login', 3, 60)],
            store: $store,
            clock: $this->frozenClock(1_000),
        );

        for ($i = 0; $i < 3; $i++) {
            $middleware->process($this->request('/api/v1/auth/login', '203.0.113.1'), $this->ok());
        }

        $response = $middleware->process(
            $this->request('/api/v1/auth/login', '203.0.113.1'),
            $this->ok(),
        );

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('60', $response->getHeaderLine('Retry-After'));
        $this->assertSame('0', $response->getHeaderLine('X-RateLimit-Remaining'));

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(429, $payload['status']);
        $this->assertSame('Too Many Requests', $payload['title']);
        $this->assertSame('/api/v1/auth/login', $payload['instance']);
    }

    public function testDistinctIpsHaveDistinctBuckets(): void
    {
        $store = new InMemoryStore();
        $middleware = new RateLimitMiddleware(
            rules: [new RateLimitRule('/api/v1/auth/login', 2, 60)],
            store: $store,
            clock: $this->frozenClock(1_000),
        );

        $middleware->process($this->request('/api/v1/auth/login', '203.0.113.1'), $this->ok());
        $middleware->process($this->request('/api/v1/auth/login', '203.0.113.1'), $this->ok());
        $third = $middleware->process($this->request('/api/v1/auth/login', '203.0.113.1'), $this->ok());

        $this->assertSame(429, $third->getStatusCode());

        $otherIp = $middleware->process($this->request('/api/v1/auth/login', '203.0.113.99'), $this->ok());
        $this->assertSame(200, $otherIp->getStatusCode());
    }

    public function testFirstMatchingRuleWins(): void
    {
        $store = new InMemoryStore();
        $middleware = new RateLimitMiddleware(
            rules: [
                new RateLimitRule('/api/v1/auth/login', 1, 60, name: 'login'),
                new RateLimitRule('/api/v1/', 100, 60, name: 'api'),
            ],
            store: $store,
            clock: $this->frozenClock(1_000),
        );

        $middleware->process($this->request('/api/v1/auth/login', '203.0.113.1'), $this->ok());
        $blocked = $middleware->process(
            $this->request('/api/v1/auth/login', '203.0.113.1'),
            $this->ok(),
        );

        $this->assertSame(429, $blocked->getStatusCode());
    }

    public function testTrustProxyHonoursForwardedHeader(): void
    {
        $store = new InMemoryStore();
        $middleware = new RateLimitMiddleware(
            rules: [new RateLimitRule('/api/v1/auth/login', 1, 60)],
            store: $store,
            clock: $this->frozenClock(1_000),
            trustProxy: true,
        );

        $first = (new ServerRequest('POST', '/api/v1/auth/login', serverParams: ['REMOTE_ADDR' => '10.0.0.1']))
            ->withHeader('X-Forwarded-For', '198.51.100.7, 10.0.0.1');
        $second = (new ServerRequest('POST', '/api/v1/auth/login', serverParams: ['REMOTE_ADDR' => '10.0.0.1']))
            ->withHeader('X-Forwarded-For', '198.51.100.7, 10.0.0.1');

        $middleware->process($first, $this->ok());
        $blocked = $middleware->process($second, $this->ok());

        $this->assertSame(429, $blocked->getStatusCode(), 'Both forwarded from 198.51.100.7');

        $different = (new ServerRequest('POST', '/api/v1/auth/login', serverParams: ['REMOTE_ADDR' => '10.0.0.1']))
            ->withHeader('X-Forwarded-For', '198.51.100.99');
        $ok = $middleware->process($different, $this->ok());
        $this->assertSame(200, $ok->getStatusCode());
    }

    public function testUserKeyStrategyUsesAuthenticatedUserId(): void
    {
        $store = new InMemoryStore();
        $middleware = new RateLimitMiddleware(
            rules: [new RateLimitRule('/api/v1/auth/me', 1, 60, keyStrategy: RateLimitRule::KEY_USER)],
            store: $store,
            clock: $this->frozenClock(1_000),
        );

        $user = new class { public int $id = 42; };
        $first = $this->request('/api/v1/auth/me', '203.0.113.1')->withAttribute('authUser', $user);
        $second = $this->request('/api/v1/auth/me', '203.0.113.1')->withAttribute('authUser', $user);

        $middleware->process($first, $this->ok());
        $blocked = $middleware->process($second, $this->ok());

        $this->assertSame(429, $blocked->getStatusCode(), 'Same user id, same bucket.');

        $otherUser = new class { public int $id = 99; };
        $otherReq = $this->request('/api/v1/auth/me', '203.0.113.1')->withAttribute('authUser', $otherUser);
        $ok = $middleware->process($otherReq, $this->ok());
        $this->assertSame(200, $ok->getStatusCode());
    }

    private function request(string $path, string $ip): ServerRequestInterface
    {
        return new ServerRequest('GET', $path, serverParams: ['REMOTE_ADDR' => $ip]);
    }

    private function ok(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'ok');
            }
        };
    }

    private function frozenClock(int $timestamp): ClockInterface
    {
        return new class($timestamp) implements ClockInterface {
            public function __construct(private readonly int $timestamp) {}
            public function now(): DateTimeImmutable
            {
                return (new DateTimeImmutable('@' . $this->timestamp))->setTimezone(new DateTimeZone('UTC'));
            }
        };
    }
}
