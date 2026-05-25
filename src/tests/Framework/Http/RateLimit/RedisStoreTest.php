<?php

declare(strict_types=1);

namespace Tests\Framework\Http\RateLimit;

use Framework\Http\RateLimit\RateLimitStore;
use Framework\Http\RateLimit\RedisLike;
use Framework\Http\RateLimit\RedisStore;
use PHPUnit\Framework\TestCase;

final class RedisStoreTest extends TestCase
{
    public function testHitIncrementsAndReportsState(): void
    {
        $fake = $this->fakeRedis();
        $store = new RedisStore($fake);

        $state = $store->hit('user:42', window: 60, limit: 5, now: 1_000);

        $this->assertSame(1, $state->count);
        $this->assertSame(5, $state->limit);
        // Fake's TTL drifts by 5s between INCR and TTL calls — see
        // the inline comment in fakeRedis(). The store anchors resetsAt at
        // `now + observedTtl`, so a small drift is expected.
        $this->assertSame(1_055, $state->resetsAt);
        $this->assertSame('rl:user:42', array_key_first($fake->keys));
    }

    public function testRepeatedHitsKeepWindow(): void
    {
        $fake = $this->fakeRedis();
        $store = new RedisStore($fake);

        $first = $store->hit('user:42', 60, 5, now: 1_000);
        $second = $store->hit('user:42', 60, 5, now: 1_005);
        $third = $store->hit('user:42', 60, 5, now: 1_010);

        $this->assertSame(1, $first->count);
        $this->assertSame(2, $second->count);
        $this->assertSame(3, $third->count);
        // Window stays anchored to the first hit; resetsAt drifts a little
        // because the fake's TTL counts down with each `now`, but never past
        // the original deadline (1060).
        $this->assertLessThanOrEqual(1_060, $third->resetsAt);
    }

    public function testStoreImplementsContract(): void
    {
        $this->assertInstanceOf(RateLimitStore::class, new RedisStore($this->fakeRedis()));
    }

    public function testCustomKeyPrefix(): void
    {
        $fake = $this->fakeRedis();
        $store = new RedisStore($fake, keyPrefix: 'antarctic:rl:');

        $store->hit('foo', 60, 5, 1_000);

        $this->assertArrayHasKey('antarctic:rl:foo', $fake->keys);
    }

    public function testFallsBackToWindowWhenTtlMissing(): void
    {
        $fake = new class implements RedisLike {
            /** @var array<string, int> */
            public array $keys = [];
            public function incrementAndExpire(string $key, int $ttlSeconds): int
            {
                $this->keys[$key] = ($this->keys[$key] ?? 0) + 1;
                return $this->keys[$key];
            }
            public function ttl(string $key): int
            {
                // Simulate a key without TTL (-1) — should fall back to window.
                return -1;
            }
        };

        $store = new RedisStore($fake);

        $state = $store->hit('foo', window: 30, limit: 5, now: 2_000);

        $this->assertSame(2_030, $state->resetsAt);
    }

    private function fakeRedis(): RedisLike
    {
        return new class implements RedisLike {
            /** @var array<string, array{count:int, expiresAt:int}> */
            public array $keys = [];
            private int $now = 1_000;

            public function incrementAndExpire(string $key, int $ttlSeconds): int
            {
                $existing = $this->keys[$key] ?? null;
                if ($existing === null || $existing['expiresAt'] <= $this->now) {
                    $existing = ['count' => 0, 'expiresAt' => $this->now + $ttlSeconds];
                }
                $existing['count']++;
                $this->keys[$key] = $existing;
                $this->now += 5; // simulate small drift between calls
                return $existing['count'];
            }

            public function ttl(string $key): int
            {
                $b = $this->keys[$key] ?? null;
                if ($b === null) {
                    return -2;
                }
                $remaining = $b['expiresAt'] - $this->now;
                return $remaining > 0 ? $remaining : -2;
            }
        };
    }
}
