<?php

declare(strict_types=1);

namespace Tests\Framework\Routing;

use Framework\Routing\RouteCache;
use PHPUnit\Framework\TestCase;

final class RouteCacheTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/antarctic-route-cache-' . bin2hex(random_bytes(4)) . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmp)) {
            unlink($this->tmp);
        }
    }

    public function testLoadReturnsNullWhenCacheMissing(): void
    {
        $cache = new RouteCache($this->tmp);
        $this->assertNull($cache->load());
    }

    public function testSaveAndLoadRoundtrip(): void
    {
        $cache = new RouteCache($this->tmp);
        $routes = [
            '/^\/foo$/i' => ['controller' => 'Foo', 'action' => 'bar', 'method' => 'GET'],
        ];
        $cache->save($routes);
        $this->assertSame($routes, $cache->load());
    }

    public function testClearRemovesFile(): void
    {
        $cache = new RouteCache($this->tmp);
        $cache->save(['/^\/foo$/i' => ['x' => 1]]);
        $cache->clear();
        $this->assertFalse(is_file($this->tmp));
        $this->assertNull($cache->load());
        $cache->clear(); // idempotent
    }

    public function testLoadIgnoresWrongVersion(): void
    {
        file_put_contents($this->tmp, "<?php return ['version' => 999, 'routes' => []];\n");
        $cache = new RouteCache($this->tmp);
        $this->assertNull($cache->load());
    }

    public function testLoadIgnoresCorruptedFile(): void
    {
        file_put_contents($this->tmp, "<?php return 42;\n");
        $cache = new RouteCache($this->tmp);
        $this->assertNull($cache->load());
    }
}
