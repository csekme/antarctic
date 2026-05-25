<?php

declare(strict_types=1);

namespace Tests\Framework\Http\RateLimit;

use Framework\Http\RateLimit\RateLimitRule;
use PHPUnit\Framework\TestCase;

final class RateLimitRuleTest extends TestCase
{
    public function testMatchesPathPrefix(): void
    {
        $rule = new RateLimitRule('/api/v1/auth/', 5, 60);

        $this->assertTrue($rule->matches('/api/v1/auth/login'));
        $this->assertTrue($rule->matches('/api/v1/auth/2fa/verify'));
        $this->assertFalse($rule->matches('/api/v1/users'));
        $this->assertFalse($rule->matches('/api/v2/auth/login'));
    }

    public function testIdUsesNameWhenProvided(): void
    {
        $rule = new RateLimitRule('/x', 5, 60, name: 'custom');
        $this->assertSame('custom', $rule->id());
    }

    public function testIdFallsBackToComposite(): void
    {
        $rule = new RateLimitRule('/api/v1/', 120, 60, keyStrategy: 'user');
        $this->assertSame('/api/v1/:user:120/60', $rule->id());
    }
}
