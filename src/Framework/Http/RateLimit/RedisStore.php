<?php

declare(strict_types=1);

namespace Framework\Http\RateLimit;

/**
 * Shared rate-limit backend for multi-worker FPM/k8s deployments. Buckets
 * are keyed `{prefix}{ruleKey}` so several apps can share one Redis without
 * stepping on each other.
 *
 * The atomicity guarantee lives in the {@see RedisLike} adapter — see its
 * docblock. This store only computes `resetsAt` from the bucket TTL.
 */
final class RedisStore implements RateLimitStore
{
    public function __construct(
        private readonly RedisLike $client,
        private readonly string $keyPrefix = 'rl:',
    ) {
    }

    public function hit(string $key, int $window, int $limit, int $now): RateLimitState
    {
        $redisKey = $this->keyPrefix . $key;
        $count = $this->client->incrementAndExpire($redisKey, $window);

        $ttl = $this->client->ttl($redisKey);
        // A negative TTL means the key has no expiry (-1) or has just been
        // evicted (-2); treat both as "window starts now" so callers still
        // see a sensible reset time.
        $resetsAt = $ttl > 0 ? $now + $ttl : $now + $window;

        return new RateLimitState(
            count: $count,
            limit: $limit,
            resetsAt: $resetsAt,
        );
    }
}
