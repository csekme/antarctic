<?php

declare(strict_types=1);

namespace Tests\Framework\Http\RateLimit;

use Framework\Http\RateLimit\RateLimitConfig;
use Framework\Http\RateLimit\RateLimitRule;
use PHPUnit\Framework\TestCase;

final class RateLimitConfigTest extends TestCase
{
    public function testEnabledFlagDefaultsToFalse(): void
    {
        $this->assertFalse(RateLimitConfig::isEnabled([]));
        $this->assertTrue(RateLimitConfig::isEnabled(['enabled' => true]));
        $this->assertFalse(RateLimitConfig::isEnabled(['enabled' => 0]));
    }

    public function testTrustProxyDefaultsToFalse(): void
    {
        $this->assertFalse(RateLimitConfig::trustProxy([]));
        $this->assertTrue(RateLimitConfig::trustProxy(['trust_proxy' => true]));
    }

    public function testRulesAreParsedAndOrderPreserved(): void
    {
        $rules = RateLimitConfig::rulesFromArray([
            'rules' => [
                ['path_prefix' => '/api/v1/auth/login', 'limit' => 5, 'window' => 60, 'name' => 'login'],
                ['path_prefix' => '/api/v1/', 'limit' => 120, 'window' => 60, 'key' => 'ip'],
            ],
        ]);

        $this->assertCount(2, $rules);
        $this->assertInstanceOf(RateLimitRule::class, $rules[0]);
        $this->assertSame('/api/v1/auth/login', $rules[0]->pathPrefix);
        $this->assertSame(5, $rules[0]->limit);
        $this->assertSame('login', $rules[0]->name);
        $this->assertSame('/api/v1/', $rules[1]->pathPrefix);
    }

    public function testInvalidEntriesAreSkipped(): void
    {
        $rules = RateLimitConfig::rulesFromArray([
            'rules' => [
                ['path_prefix' => '/ok', 'limit' => 5, 'window' => 60],
                'not-an-array',
                ['path_prefix' => '/missing-limit', 'window' => 60],
                ['path_prefix' => '/zero-limit', 'limit' => 0, 'window' => 60],
                ['path_prefix' => '/zero-window', 'limit' => 5, 'window' => 0],
            ],
        ]);

        $this->assertCount(1, $rules);
        $this->assertSame('/ok', $rules[0]->pathPrefix);
    }

    public function testEmptyConfigYieldsEmptyRules(): void
    {
        $this->assertSame([], RateLimitConfig::rulesFromArray([]));
        $this->assertSame([], RateLimitConfig::rulesFromArray(['rules' => 'oops']));
    }

    public function testKeyStrategyDefaultsToIp(): void
    {
        $rules = RateLimitConfig::rulesFromArray([
            'rules' => [['path_prefix' => '/x', 'limit' => 1, 'window' => 1]],
        ]);
        $this->assertSame(RateLimitRule::KEY_IP, $rules[0]->keyStrategy);
    }
}
