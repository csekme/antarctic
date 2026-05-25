<?php

declare(strict_types=1);

namespace Framework\Http\RateLimit;

/**
 * Minimal protocol the {@see RedisStore} needs from a Redis client. By
 * abstracting against this interface we avoid pinning the codebase to a
 * specific client (predis, phpredis, RoadRunner KV, etc.) and keep tests
 * driver-free.
 *
 * Implementations must guarantee that `incrementAndExpire` is atomic on the
 * server: a single `MULTI`/`EXEC` or a Lua script. Naïvely separate `INCR`
 * + `EXPIRE` calls race — between the two commands, another process can
 * read a key without an expiry and the bucket leaks forever.
 */
interface RedisLike
{
    /**
     * Atomically increments the counter at `$key` and ensures the key has a
     * TTL. Implementations should only set the TTL on the first increment
     * (when the counter went from 0 to 1), so that a long-running burst
     * does not extend the window.
     *
     * @return int The new counter value after the increment.
     */
    public function incrementAndExpire(string $key, int $ttlSeconds): int;

    /**
     * Returns the remaining TTL on the key in seconds, or -1 if the key has
     * no TTL, or -2 if the key is missing. Matches the Redis `TTL` contract.
     */
    public function ttl(string $key): int;
}
