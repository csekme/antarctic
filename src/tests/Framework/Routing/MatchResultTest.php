<?php

declare(strict_types=1);

namespace Tests\Framework\Routing;

use Framework\Routing\MatchResult;
use PHPUnit\Framework\TestCase;

final class MatchResultTest extends TestCase
{
    public function testFoundExposesParams(): void
    {
        $result = MatchResult::found(['controller' => 'X', 'action' => 'y']);
        $this->assertTrue($result->isFound());
        $this->assertFalse($result->isMethodNotAllowed());
        $this->assertFalse($result->isNotFound());
        $this->assertSame('X', $result->params['controller']);
        $this->assertSame([], $result->allowedMethods);
    }

    public function testMethodNotAllowedHasAllowList(): void
    {
        $result = MatchResult::methodNotAllowed(['GET', 'POST', 'GET']);
        $this->assertTrue($result->isMethodNotAllowed());
        $this->assertFalse($result->isFound());
        $this->assertSame(['GET', 'POST'], $result->allowedMethods); // unique-elt
    }

    public function testNotFoundExposesEmptyState(): void
    {
        $result = MatchResult::notFound();
        $this->assertTrue($result->isNotFound());
        $this->assertFalse($result->isFound());
        $this->assertSame([], $result->params);
        $this->assertSame([], $result->allowedMethods);
    }
}
