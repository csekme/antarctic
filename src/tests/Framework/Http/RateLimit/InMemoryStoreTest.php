<?php

declare(strict_types=1);

namespace Tests\Framework\Http\RateLimit;

use Framework\Http\RateLimit\InMemoryStore;
use PHPUnit\Framework\TestCase;

final class InMemoryStoreTest extends TestCase
{
    public function testFirstHitInitialisesBucket(): void
    {
        $store = new InMemoryStore();
        $state = $store->hit('k', 60, 5, 1_000);

        $this->assertSame(1, $state->count);
        $this->assertSame(5, $state->limit);
        $this->assertSame(1_060, $state->resetsAt);
        $this->assertFalse($state->isExceeded());
        $this->assertSame(4, $state->remaining());
    }

    public function testSequentialHitsIncrementWithinWindow(): void
    {
        $store = new InMemoryStore();
        $store->hit('k', 60, 5, 1_000);
        $store->hit('k', 60, 5, 1_010);
        $state = $store->hit('k', 60, 5, 1_020);

        $this->assertSame(3, $state->count);
        $this->assertSame(1_060, $state->resetsAt);
        $this->assertSame(2, $state->remaining());
    }

    public function testHitsForDifferentKeysAreIndependent(): void
    {
        $store = new InMemoryStore();
        $store->hit('a', 60, 5, 1_000);
        $store->hit('a', 60, 5, 1_010);
        $stateB = $store->hit('b', 60, 5, 1_020);

        $this->assertSame(1, $stateB->count);
    }

    public function testExpiredWindowResetsCounter(): void
    {
        $store = new InMemoryStore();
        $store->hit('k', 60, 5, 1_000);
        $store->hit('k', 60, 5, 1_010);
        $state = $store->hit('k', 60, 5, 1_100); // past resetsAt=1060

        $this->assertSame(1, $state->count);
        $this->assertSame(1_160, $state->resetsAt);
    }

    public function testExceededWhenCountGoesAboveLimit(): void
    {
        $store = new InMemoryStore();
        for ($i = 1; $i <= 5; $i++) {
            $store->hit('k', 60, 5, 1_000);
        }
        $state = $store->hit('k', 60, 5, 1_000);

        $this->assertSame(6, $state->count);
        $this->assertTrue($state->isExceeded());
        $this->assertSame(0, $state->remaining());
        $this->assertSame(60, $state->retryAfterSeconds(1_000));
    }

    public function testClearWipesAllBuckets(): void
    {
        $store = new InMemoryStore();
        $store->hit('k', 60, 5, 1_000);
        $store->clear();

        $state = $store->hit('k', 60, 5, 1_000);
        $this->assertSame(1, $state->count);
    }
}
