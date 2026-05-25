<?php

declare(strict_types=1);

namespace Tests\Framework\Http\RateLimit;

use Framework\Http\RateLimit\PhpRedisAdapter;
use PHPUnit\Framework\TestCase;

final class PhpRedisAdapterTest extends TestCase
{
    public function testIncrementAndExpireCallsEvalWithLuaScript(): void
    {
        $stub = new class () {
            public ?string $script = null;
            public array $args = [];
            public int $numKeys = 0;

            public function eval(string $script, array $args = [], int $numKeys = 0): int
            {
                $this->script = $script;
                $this->args = $args;
                $this->numKeys = $numKeys;
                return 4;
            }

            public function ttl(string $key): int
            {
                return -1;
            }
        };

        $adapter = new PhpRedisAdapter($stub);
        $count = $adapter->incrementAndExpire('rl:user:42', 60);

        $this->assertSame(4, $count);
        $this->assertNotNull($stub->script);
        $this->assertStringContainsString('INCR', $stub->script);
        $this->assertStringContainsString('EXPIRE', $stub->script);
        $this->assertSame(['rl:user:42', '60'], $stub->args);
        $this->assertSame(1, $stub->numKeys);
    }

    public function testIncrementAndExpireFalsyReturnCollapsesToZero(): void
    {
        $stub = new class () {
            public function eval(string $script, array $args = [], int $numKeys = 0): false
            {
                return false;
            }

            public function ttl(string $key): int
            {
                return -2;
            }
        };

        $adapter = new PhpRedisAdapter($stub);

        $this->assertSame(0, $adapter->incrementAndExpire('rl:user:42', 60));
    }

    public function testTtlForwarded(): void
    {
        $stub = new class () {
            public function eval(string $script, array $args = [], int $numKeys = 0): int
            {
                return 0;
            }

            public function ttl(string $key): int
            {
                return 42;
            }
        };

        $adapter = new PhpRedisAdapter($stub);

        $this->assertSame(42, $adapter->ttl('rl:user:42'));
    }

    public function testTtlFalsyReturnCollapsesToMinusTwo(): void
    {
        $stub = new class () {
            public function eval(string $script, array $args = [], int $numKeys = 0): int
            {
                return 0;
            }

            public function ttl(string $key): false
            {
                return false;
            }
        };

        $adapter = new PhpRedisAdapter($stub);

        $this->assertSame(-2, $adapter->ttl('rl:user:42'));
    }
}
