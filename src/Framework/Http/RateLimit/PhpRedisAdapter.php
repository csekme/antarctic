<?php

declare(strict_types=1);

namespace Framework\Http\RateLimit;

/**
 * Adapts the `ext-redis` (`\Redis`) client to {@see RedisLike}. The atomic
 * increment-with-TTL is the exact same Lua script the {@see PredisAdapter}
 * uses — only the EVAL call surface differs between the two drivers.
 *
 * The constructor type is loose (`object`) so the file parses on hosts
 * without `ext-redis` installed (PHPStan / unit-test runners). The object
 * passed in is expected to be `\Redis` (or a compatible mock).
 *
 * @phpstan-type RedisClient \Redis
 */
final class PhpRedisAdapter implements RedisLike
{
    private const INCR_SCRIPT = <<<'LUA'
local n = redis.call('INCR', KEYS[1])
if n == 1 then
  redis.call('EXPIRE', KEYS[1], ARGV[1])
end
return n
LUA;

    /** @param object $client an instance of `\Redis` (ext-redis) or compatible. */
    public function __construct(private readonly object $client)
    {
    }

    public function incrementAndExpire(string $key, int $ttlSeconds): int
    {
        // ext-redis signature: eval(string $script, array $args = [], int $num_keys = 0)
        // The single key goes first in $args; ARGV follows.
        /** @var int|false $result */
        $result = $this->client->eval(self::INCR_SCRIPT, [$key, (string) $ttlSeconds], 1);
        return $result === false ? 0 : (int) $result;
    }

    public function ttl(string $key): int
    {
        /** @var int|false $result */
        $result = $this->client->ttl($key);
        return is_int($result) ? $result : -2;
    }
}
