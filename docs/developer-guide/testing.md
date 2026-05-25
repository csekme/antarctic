# Tesztelés

Az Antarctic **PHPUnit 11**-et használ, a tesztek a `src/tests/` alatt élnek, a `Tests\` namespace alatt (autoload-dev).

## Setup

```bash
cd src
composer install                     # vagy: composer install --dev
vendor/bin/phpunit --testdox          # teljes suite
vendor/bin/phpunit tests/Framework/Http   # csak egy alkönyvtár
vendor/bin/phpunit --filter testRendersProblemJsonForApiPath
```

PHPStan (static analysis):

```bash
vendor/bin/phpstan analyse --memory-limit=512M --no-progress
```

## Strukúra

```
src/tests/
└── Framework/
    ├── ContainerTest.php
    ├── TokenTest.php
    └── Http/
        ├── ContentNegotiationTest.php
        ├── CorsMiddlewareTest.php
        ├── ErrorHandlerMiddlewareTest.php
        ├── HttpAdapterTest.php
        └── MiddlewarePipelineTest.php
```

A namespace minden test fájlban `Tests\Framework\…` — a `composer.json` autoload-dev szakasza mappeli.

## Egy egyszerű test

```php
<?php
declare(strict_types=1);

namespace Tests\Framework\Http;

use Framework\Http\ContentNegotiation;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class ContentNegotiationTest extends TestCase
{
    public function testApiPathAlwaysWantsJson(): void
    {
        $request = new ServerRequest('GET', '/api/v1/users');
        $this->assertTrue(ContentNegotiation::wantsJson($request));
    }
}
```

## Middleware tesztelés

A PSR-15 middleware-ek tisztán tesztelhetőek anonymous handler-ekkel, mert nincs szükségük futó szerverre.

### Fake handler (visszaadja a megadott választ)

```php
private function handlerReturning(ResponseInterface $response): RequestHandlerInterface
{
    return new class($response) implements RequestHandlerInterface {
        public function __construct(private readonly ResponseInterface $response) {}
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return $this->response;
        }
    };
}
```

### Fake handler (kivételt dob)

```php
private function handlerThrowing(\Throwable $exception): RequestHandlerInterface
{
    return new class($exception) implements RequestHandlerInterface {
        public function __construct(private readonly \Throwable $exception) {}
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            throw $this->exception;
        }
    };
}
```

### Példa: CORS preflight teszt

```php
public function testPreflightShortCircuitsHandler(): void
{
    $middleware = new CorsMiddleware([
        'allowed_origins' => ['https://app.example.com'],
        'allowed_methods' => ['GET', 'POST'],
    ]);

    $handlerCalled = false;
    $handler = new class($handlerCalled) implements RequestHandlerInterface {
        public function __construct(private bool &$called) {}
        public function handle(ServerRequestInterface $req): ResponseInterface
        {
            $this->called = true;
            return new Response(200);
        }
    };

    $request = (new ServerRequest('OPTIONS', '/api/v1/x'))
        ->withHeader('Origin', 'https://app.example.com')
        ->withHeader('Access-Control-Request-Method', 'POST');

    $response = $middleware->process($request, $handler);

    $this->assertFalse($handlerCalled);
    $this->assertSame(204, $response->getStatusCode());
}
```

## Pipeline integrációs teszt

Egy teljes pipeline futtatása mock middleware-ekkel:

```php
public function testFullPipelineCatchesErrors(): void
{
    $pipeline = new MiddlewarePipeline(
        [
            new ErrorHandlerMiddleware(debug: false),
            new CorsMiddleware(['allowed_origins' => ['*']]),
        ],
        $this->handlerThrowing(new \Exception('boom', 500)),
    );

    $response = $pipeline->handle(
        (new ServerRequest('GET', '/api/v1/x'))
            ->withHeader('Origin', 'https://app.example.com'),
    );

    $this->assertSame(500, $response->getStatusCode());
    $this->assertStringStartsWith('application/problem+json', $response->getHeaderLine('Content-Type'));
    $this->assertSame('https://app.example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
}
```

## Kontroller tesztelés

A kontrollereket **lehetőleg ne** integration teszttel, hanem unit teszttel ellenőrizd. Mockold az injektált függőségeket (repository, service), és hívd közvetlenül a method-ot.

```php
final class HelloControllerTest extends TestCase
{
    public function testIndexReturnsGreeting(): void
    {
        $controller = new HelloController([]);
        $controller->setRequest($this->fakeRequest());
        $controller->setResponse(new Response());

        $response = $controller->index();

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('Hello from Antarctic', $body['message']);
    }

    private function fakeRequest(): \Framework\Request
    {
        return new \Framework\Request(
            uri: '', method: 'GET',
            get: [], post: [], files: [], cookie: [], server: [],
        );
    }
}
```

## Mi tesztelhető és mi nem (jelenleg)

| Komponens | Tesztelhetőség | Megjegyzés |
|---|---|---|
| PSR-15 middleware-ek | ⭐⭐⭐ Kiváló | Fake handler + ServerRequest. |
| `HttpAdapter` | ⭐⭐⭐ Kiváló | Tisztán static, semmi side effect. |
| `MiddlewarePipeline` | ⭐⭐⭐ Kiváló | A test suite-ban már több variációval. |
| `ContentNegotiation` | ⭐⭐⭐ Kiváló | Static. |
| `Router` | ⭐⭐ Megfelelő | A `StandardRouterImpl` konstruktora végigfut a fájlrendszeren — egységteszthez érdemes mockolni vagy stub osztályokat tenni mellé. M3.b-ben javul. |
| `Dispatcher` | ⭐ Nehéz | Tele van session-, statikus DB-, kontroller-példányosítási csatolásokkal. Az M2-M3 PR-ek után tisztább lesz. |
| Twig view | ⭐ Nehéz | Globális side effecting (output buffering). Kerüld. |

## CI

A GitHub Actions workflow (M0 PR-ben hozzáadva) minden PR-en futtatja:

- `composer install`
- `vendor/bin/phpstan analyse`
- `vendor/bin/phpunit`

A workflow definíciója a repo `.github/workflows/` alatt.

## Konvenciók

- **Egy assertion = egy test method**. Ne tölts össze 10 assertet egy testbe — a debug-olást nehezíti.
- **Beszélő test name**: `testRendersProblemJsonForApiPathRegardlessOfAcceptHeader` — a viselkedést írja le, nem az implementációt.
- **`final` osztály** mindenhol — a PHPUnit teszt osztályoknak nincs okuk öröklődni.
- **`declare(strict_types=1);`** minden új fájl tetején.
- **PHPStan level 1**, level emelés ratchet-ben M5-ig.
