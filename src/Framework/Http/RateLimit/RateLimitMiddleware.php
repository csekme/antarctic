<?php

declare(strict_types=1);

namespace Framework\Http\RateLimit;

use Nyholm\Psr7\Response;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 rate-limit middleware. For each request the middleware:
 *   1. Finds the *first* rule whose `pathPrefix` matches the request path.
 *      If none matches, the request passes through untouched.
 *   2. Computes a bucket key combining the rule id and the identification
 *      strategy (IP, authenticated user id).
 *   3. Asks the {@see RateLimitStore} to atomically increment the counter.
 *   4. If the count exceeds the threshold, returns a 429 RFC 7807 problem+json
 *      response with `Retry-After` and informational `X-RateLimit-*` headers.
 *      Otherwise it lets the downstream handler run and decorates the
 *      response with the same informational headers.
 *
 * The clock is injected to keep the time source explicit — production swaps
 * a {@see \Framework\Auth\SystemClock}, tests pass a {@see FakeClock} via
 * an inline `ClockInterface` implementation.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * @param list<RateLimitRule> $rules
     */
    public function __construct(
        private readonly array $rules,
        private readonly RateLimitStore $store,
        private readonly ClockInterface $clock,
        private readonly bool $trustProxy = false,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $rule = $this->matchRule($path);
        if ($rule === null) {
            return $handler->handle($request);
        }

        $now = $this->clock->now()->getTimestamp();
        $key = $rule->id() . '|' . $this->bucketKey($request, $rule);
        $state = $this->store->hit($key, $rule->window, $rule->limit, $now);

        if ($state->isExceeded()) {
            return $this->throttleResponse($request, $state, $now);
        }

        $response = $handler->handle($request);
        return $this->decorate($response, $state, $now);
    }

    private function matchRule(string $path): ?RateLimitRule
    {
        foreach ($this->rules as $rule) {
            if ($rule->matches($path)) {
                return $rule;
            }
        }
        return null;
    }

    private function bucketKey(ServerRequestInterface $request, RateLimitRule $rule): string
    {
        if ($rule->keyStrategy === RateLimitRule::KEY_USER) {
            $user = $request->getAttribute('authUser');
            if (is_object($user) && property_exists($user, 'id')) {
                return 'u:' . (string) $user->id;
            }
        }
        return 'ip:' . $this->clientIp($request);
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        if ($this->trustProxy) {
            $forwarded = $request->getHeaderLine('X-Forwarded-For');
            if ($forwarded !== '') {
                $first = trim(explode(',', $forwarded)[0]);
                if ($first !== '') {
                    return $first;
                }
            }
        }
        $server = $request->getServerParams();
        return (string) ($server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    private function throttleResponse(ServerRequestInterface $request, RateLimitState $state, int $now): ResponseInterface
    {
        $retry = $state->retryAfterSeconds($now);
        $payload = [
            'type' => 'about:blank',
            'title' => 'Too Many Requests',
            'status' => 429,
            'detail' => sprintf('Rate limit exceeded. Retry in %d seconds.', $retry),
            'instance' => $request->getUri()->getPath(),
        ];
        $body = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new Response(
            429,
            [
                'Content-Type' => 'application/problem+json; charset=utf-8',
                'Retry-After' => (string) $retry,
                'X-RateLimit-Limit' => (string) $state->limit,
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset' => (string) $state->resetsAt,
            ],
            $body,
        );
    }

    private function decorate(ResponseInterface $response, RateLimitState $state, int $now): ResponseInterface
    {
        return $response
            ->withHeader('X-RateLimit-Limit', (string) $state->limit)
            ->withHeader('X-RateLimit-Remaining', (string) $state->remaining())
            ->withHeader('X-RateLimit-Reset', (string) $state->resetsAt);
    }
}
