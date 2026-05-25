<?php

declare(strict_types=1);

namespace Framework\Http\RateLimit;

/**
 * Backend abstraction for the rate-limit middleware. Implementations are
 * expected to be atomic — concurrent processes hitting the same key should
 * each observe a strictly monotonically increasing count, otherwise short
 * bursts can leak past the limit.
 *
 * Two production-grade implementations are easy to add later:
 *   - Redis: `INCR <key>` + `EXPIRE` is naturally atomic, single round-trip.
 *   - Memcached: `increment` + initial `add` works the same way.
 *
 * The {@see InMemoryStore} in this package is intentionally process-local
 * (dev, tests, single-worker SAPI).
 */
interface RateLimitStore
{
    /**
     * Atomically increment the counter for `$key` and return the updated
     * state. If the key is unseen or expired, a new window starts now and
     * the count is initialised to 1.
     *
     * @param int $window TTL of the bucket, in seconds.
     * @param int $limit The threshold that callers will compare `count` against.
     */
    public function hit(string $key, int $window, int $limit, int $now): RateLimitState;
}
