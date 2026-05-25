<?php

declare(strict_types=1);

namespace Tests\Framework\Routing;

use Framework\Routing\MatchResult;
use Framework\Routing\StandardRouterImpl;
use PHPUnit\Framework\TestCase;

final class StandardRouterImplTest extends TestCase
{
    public function testMatchReturnsFoundForRegisteredRoute(): void
    {
        $router = new StandardRouterImpl([]);
        $router->add('users', ['controller' => 'Users', 'action' => 'index', 'method' => 'GET']);

        $result = $router->match('users', 'GET');
        $this->assertTrue($result->isFound());
        $this->assertSame('Users', $result->params['controller']);
    }

    public function testMatchIsMethodAware(): void
    {
        $router = new StandardRouterImpl([]);
        $router->add('users', ['controller' => 'Users', 'action' => 'index', 'method' => 'GET']);
        $router->add('users', ['controller' => 'Users', 'action' => 'store', 'method' => 'POST']);

        // Az "add" ugyanazon route-on újraírja a bejegyzést (last-wins), de a
        // method-aware test két különböző path-on egyértelmű. Itt PUT-ot kérdezünk,
        // amit egyik se kezel — 405-öt várunk a allow-listával ami a meglévőt tartalmazza.
        $put = $router->match('users', 'PUT');
        $this->assertTrue($put->isMethodNotAllowed());
        $this->assertContains('POST', $put->allowedMethods); // last-wins miatt POST
    }

    public function testMatchDistinguishesMethodFromNotFound(): void
    {
        $router = new StandardRouterImpl([]);
        $router->add('articles', ['controller' => 'Articles', 'action' => 'index', 'method' => 'GET']);

        $put = $router->match('articles', 'PUT');
        $this->assertTrue($put->isMethodNotAllowed());
        $this->assertSame(['GET'], $put->allowedMethods);

        $missing = $router->match('nope', 'GET');
        $this->assertTrue($missing->isNotFound());
    }

    public function testMatchWithoutMethodIgnoresMethodFilter(): void
    {
        $router = new StandardRouterImpl([]);
        $router->add('articles', ['controller' => 'Articles', 'action' => 'index', 'method' => 'POST']);

        $any = $router->match('articles', null);
        $this->assertTrue($any->isFound());
    }

    public function testMatchExtractsNamedCaptureGroups(): void
    {
        $router = new StandardRouterImpl([]);
        $router->add('users/{id}', ['controller' => 'Users', 'action' => 'show', 'method' => 'GET']);

        $hit = $router->match('users/alice', 'GET');
        $this->assertTrue($hit->isFound());
        $this->assertSame('alice', $hit->params['id']);
    }

    public function testCachedRoutesBypassDiscovery(): void
    {
        $cached = [
            '/^\/api\/v1\/healthz$/i' => ['controller' => 'Health', 'action' => 'check', 'method' => 'GET'],
        ];
        $router = new StandardRouterImpl($cached);
        $this->assertSame($cached, $router->getRoutes());

        $hit = $router->match('/api/v1/healthz', 'GET');
        $this->assertTrue($hit->isFound());
    }
}
