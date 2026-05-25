<?php

declare(strict_types=1);

namespace Framework\Routing;

interface Router
{
    /**
     * Add a route to the routing table.
     *
     * @param string $route  The route URL pattern
     * @param array<string, mixed> $params Parameters (controller, action, method, etc.)
     */
    public function add(string $route, array $params = []): void;

    /**
     * Match the URL against the registered routes.
     *
     * Method-aware: ha egy route URL-re illeszkedik, de a HTTP metódusa nem,
     * `MatchResult::methodNotAllowed`-t ad — különböztetve a 405 / 404
     * helyzetet. Ha a `$method` `null`, csak URL-re illeszt (legacy mód).
     */
    public function match(string $url, ?string $method = null): MatchResult;
}
