<?php

declare(strict_types=1);

namespace Framework\Http\RateLimit;

use Predis\ClientInterface;

/**
 * Adapts a `predis/predis` client to {@see RedisLike}. The atomic
 * increment-with-TTL is implemented as a single Lua script (`EVAL`); Redis
 * runs scripts atomically, so concurrent processes hitting the same bucket
 * key are serialised on the server.
 *
 * For phpredis (`ext-redis`), write a parallel adapter — the script body is
 * identical, only the call surface changes.
 */
final class PredisAdapter implements RedisLike
{
    /**
     * KEYS[1] = bucket key, ARGV[1] = window seconds.
     *
     *   1. INCR the key (creates it at 1 if absent).
     *   2. If the new count is 1, set the TTL — first hit starts the window.
     *   3. Return the new count.
     */
    private const INCR_SCRIPT = <<<'LUA'
local n = redis.call('INCR', KEYS[1])
if n == 1 then
  redis.call('EXPIRE', KEYS[1], ARGV[1])
end
return n
LUA;

    public function __construct(private readonly ClientInterface $client)
    {
    }

    public function incrementAndExpire(string $key, int $ttlSeconds): int
    {
        /** @var int|string $result */
        $result = $this->client->eval(self::INCR_SCRIPT, 1, $key, (string) $ttlSeconds);
        return (int) $result;
    }

    public function ttl(string $key): int
    {
        /** @var int|string $result */
        $result = $this->client->ttl($key);
        return (int) $result;
    }
}
