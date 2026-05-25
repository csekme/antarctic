<?php

declare(strict_types=1);

namespace Framework\Http\RateLimit;

/**
 * Process-local store. Buckets reset when the PHP process dies — useful for
 * dev, tests, and single-worker SAPIs where one process serves all requests
 * (RoadRunner, ReactPHP, the PHPUnit suite). Multi-worker FPM deployments
 * **must** swap in a shared backend (Redis/Memcached), otherwise a single
 * user can hit the same limit N times in parallel where N is the worker
 * pool size.
 */
final class InMemoryStore implements RateLimitStore
{
    /** @var array<string, array{count:int, resetsAt:int}> */
    private array $buckets = [];

    public function hit(string $key, int $window, int $limit, int $now): RateLimitState
    {
        $bucket = $this->buckets[$key] ?? null;
        if ($bucket === null || $bucket['resetsAt'] <= $now) {
            $bucket = ['count' => 0, 'resetsAt' => $now + $window];
        }
        $bucket['count']++;
        $this->buckets[$key] = $bucket;

        return new RateLimitState(
            count: $bucket['count'],
            limit: $limit,
            resetsAt: $bucket['resetsAt'],
        );
    }

    public function clear(): void
    {
        $this->buckets = [];
    }
}
