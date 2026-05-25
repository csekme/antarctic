<?php

declare(strict_types=1);

namespace Tests\Framework;

use Framework\ContainerFactory;
use Framework\Controller;
use Framework\Dispatcher;
use Framework\Request;
use Framework\Response;
use Framework\Routing\MatchResult;
use Framework\Routing\Router;
use PHPUnit\Framework\TestCase;

/**
 * Integration coverage for the M3.d wiring: controllers come out of the
 * php-di {@see \DI\FactoryInterface} `make()` call with route params merged
 * onto autowired constructor deps, and every dispatch gets a fresh Response.
 */
final class DispatcherTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SESSION = [];
    }

    public function testControllerDependenciesAreAutowired(): void
    {
        $dispatcher = new Dispatcher($this->routerForAction('greet'), ContainerFactory::build());

        $response = $dispatcher->handleRequest($this->request());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('"id":"greeter-service"', $response->getBody());
    }

    public function testEachDispatchGetsFreshResponse(): void
    {
        $dispatcher = new Dispatcher($this->routerForAction('greet'), ContainerFactory::build());

        $first = $dispatcher->handleRequest($this->request());
        $second = $dispatcher->handleRequest($this->request());

        $this->assertNotSame(
            $first,
            $second,
            'Dispatcher must hand each request a fresh Response — long-running '
            . 'workers reuse the container and a shared Response would leak.',
        );
    }

    public function testRouteParamsArrivedAtLegacyController(): void
    {
        $dispatcher = new Dispatcher($this->routerForAction('echoParams'), ContainerFactory::build());

        $response = $dispatcher->handleRequest($this->request());

        $this->assertStringContainsString('"controller":"di-controller"', $response->getBody());
    }

    private function routerForAction(string $action): Router
    {
        return new class ($action) implements Router {
            public function __construct(private readonly string $action)
            {
            }

            public function add(string $route, array $params = []): void
            {
            }

            public function match(string $url, ?string $method = null): MatchResult
            {
                return MatchResult::found([
                    'controller' => 'di-controller',
                    'action' => $this->action,
                    'namespace' => 'Tests\\Framework',
                ]);
            }
        };
    }

    private function request(): Request
    {
        return new Request(
            uri: '/api/v1/test',
            method: 'GET',
            get: [],
            post: [],
            files: [],
            cookie: [],
            server: ['REQUEST_METHOD' => 'GET'],
        );
    }
}

final class GreeterService
{
    public string $id = 'greeter-service';
}

/**
 * Lives in `Tests\Framework` so the test's fake router can resolve
 * `controller=di-controller, namespace=Tests\Framework` → this class.
 */
class DiController extends Controller
{
    public function __construct(
        public readonly GreeterService $greeter,
        array $route_params = [],
    ) {
        parent::__construct($route_params);
    }

    public function greet(): Response
    {
        return Response::json(['id' => $this->greeter->id]);
    }

    public function echoParams(): Response
    {
        return Response::json(['controller' => $this->route_params['controller'] ?? null]);
    }
}
