<?php

declare(strict_types=1);

namespace Tests\Framework\Http\RateLimit;

use Framework\Http\RateLimit\PredisAdapter;
use PHPUnit\Framework\TestCase;
use Predis\ClientInterface;

final class PredisAdapterTest extends TestCase
{
    public function testIncrementAndExpireCallsEvalWithLuaScript(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects($this->once())
            ->method('__call')
            ->with(
                'eval',
                $this->callback(function (array $args): bool {
                    return is_string($args[0])
                        && str_contains($args[0], 'INCR')
                        && str_contains($args[0], 'EXPIRE')
                        && $args[1] === 1
                        && $args[2] === 'rl:user:42'
                        && $args[3] === '60';
                }),
            )
            ->willReturn(3);

        $adapter = new PredisAdapter($client);
        $count = $adapter->incrementAndExpire('rl:user:42', 60);

        $this->assertSame(3, $count);
    }

    public function testTtlForwarded(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects($this->once())
            ->method('__call')
            ->with('ttl', ['rl:user:42'])
            ->willReturn(42);

        $adapter = new PredisAdapter($client);

        $this->assertSame(42, $adapter->ttl('rl:user:42'));
    }
}
