<?php

declare(strict_types=1);

namespace Framework\Console;

use Framework\Routing\RouteCache;
use Framework\Routing\StandardRouterImpl;

/**
 * Build / clear a route cache file. Production deploy step:
 *
 *   bin/console route:cache         # generate var/cache/routes.php
 *   bin/console route:cache --clear # remove the cache
 *
 * A `Bootstrap` ezt automatikusan használja: ha létezik a cache, betölti;
 * egyébként reflection-szel scanneli a controllereket request-időben.
 */
final class RouteCacheCommand implements Command
{
    public function __construct(private readonly RouteCache $cache)
    {
    }

    public function name(): string
    {
        return 'route:cache';
    }

    public function description(): string
    {
        return 'Builds (or clears with --clear) the precompiled route cache.';
    }

    public function run(array $argv): int
    {
        $clear = false;
        foreach ($argv as $arg) {
            if ($arg === '--clear') {
                $clear = true;
            } else {
                fwrite(STDERR, "Unknown argument: {$arg}\n");
                return 1;
            }
        }

        if ($clear) {
            $this->cache->clear();
            echo "Cleared route cache: {$this->cache->path()}\n";
            return 0;
        }

        $routes = StandardRouterImpl::discoverRoutes();
        $this->cache->save($routes);
        echo "Wrote " . count($routes) . " route(s) to {$this->cache->path()}\n";
        return 0;
    }
}
