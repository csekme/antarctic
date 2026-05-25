# Middleware pipeline

A keretrendszer minden HTTP kérést egy [PSR-15](https://www.php-fig.org/psr/psr-15/) middleware pipeline-on vezet keresztül. Egy middleware felelős egyetlen ortogonális feladatért (CORS, hibakezelés, auth, log, stb.) — és minden middleware tetszőlegesen sorba köthető.

## Mi az a middleware?

Egy middleware egy `Psr\Http\Server\MiddlewareInterface`-t implementáló osztály:

```php
public function process(
    ServerRequestInterface $request,
    RequestHandlerInterface $handler
): ResponseInterface;
```

Két dolgot tehet:

1. **Before**: módosíthatja a requestet, mielőtt továbbadja: `$handler->handle($modifiedRequest)`.
2. **After**: módosíthatja a választ, miután a következő handler visszaadta.

Egyszerű példa — egy `X-Server-Time` header hozzáadó middleware:

```php
namespace Application\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ServerTimeMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $response = $handler->handle($request);
        return $response->withHeader('X-Server-Time', (string) time());
    }
}
```

## A pipeline összerakása

A `Framework\Http\MiddlewarePipeline` egy `RequestHandlerInterface`, ami:

- Kap egy listányi middleware-t és egy fallback handlert.
- Minden `handle()` híváskor egy `clone`-olt példányon halad tovább a következő middleware-re.
- Ha kifogytak a middleware-ek, a fallback handlert hívja.

A pipeline összeállítása a `Bootstrap.php`-ban történik:

```php
$pipeline = new MiddlewarePipeline(
    [
        new ErrorHandlerMiddleware(debug: $debug),
        new CorsMiddleware($corsConfig),
        new LegacyDispatcherMiddleware($dispatcher),
    ],
    new NotFoundHandler(),
);

(new SapiEmitter())->emit($pipeline->handle($request));
```

## Pipeline-sorrend

A sorrend **számít**, mert minden middleware egyaránt láthatja a beérkező requestet ÉS a kimenő választ:

- A pipeline elején lévő middleware **legkorábban látja a requestet** és **legkésőbb látja a választ**.
- A pipeline végén lévő ennek a fordítottja.

```text
Request  ────▶  ErrorHandler ──▶ Cors ──▶ LegacyDispatcher ──▶ NotFound
                                                                  │
Response ◀─── ErrorHandler ◀── Cors ◀── LegacyDispatcher ◀────────┘
```

**Hüvelykujj-szabály**:

- A hibakezelőnek **legkívülre** kell kerülnie (mindent be tud kapni).
- A CORS-nak elöl, de a hibakezelő után — hogy a hibaválaszok is megkapják a CORS headereket.
- Az authentikáció a routing UTÁN, mert az kell hozzá hogy melyik kontroller mit követel meg.
- A logging / request-id legelöl, hogy minden későbbi middleware lássa.

## Új middleware regisztrálása

Egészen az M3.c PR-ig (PSR-11 container csere) a middleware-eket kézzel rakod a Bootstrap pipeline listájába:

```php
// Bootstrap.php
$pipeline = new MiddlewarePipeline(
    [
        new ErrorHandlerMiddleware(debug: $debug),
        new \Application\Http\Middleware\RequestLoggerMiddleware($logger),
        new CorsMiddleware($corsConfig),
        new LegacyDispatcherMiddleware($dispatcher),
    ],
    new NotFoundHandler(),
);
```

!!! tip "Saját middleware-eket az `Application\Http\Middleware\` alá tedd"
    A `Framework\Http\` namespace a keretrendszer saját middleware-jeinek van fenntartva. A saját kódod az `Application\` namespace alá kerüljön.

## A pipeline tesztelése

A `MiddlewarePipeline` tisztán PSR-15, így bármely fake handler/middleware-rel tesztelhető. Mintapélda a [`tests/Framework/Http/MiddlewarePipelineTest.php`](https://github.com/csekme/antarctic/blob/main/src/tests/Framework/Http/MiddlewarePipelineTest.php).

```php
$pipeline = new MiddlewarePipeline([$myMiddleware], $fallbackHandler);
$response = $pipeline->handle(new ServerRequest('GET', '/path'));
$this->assertSame(200, $response->getStatusCode());
```

## Referencia: built-in middleware-ek

| Osztály | Fájl | Funkció |
|---|---|---|
| `ErrorHandlerMiddleware` | `Framework/Http/ErrorHandlerMiddleware.php` | Throwable → response, RFC 7807 vagy HTML |
| `CorsMiddleware` | `Framework/Http/CorsMiddleware.php` | CORS allow-list + preflight |
| `LegacyDispatcherMiddleware` | `Framework/Http/LegacyDispatcherMiddleware.php` | Wrap a régi `Dispatcher`-en |
| `NotFoundHandler` | `Framework/Http/NotFoundHandler.php` | Terminális 404 fallback |
| `HttpAdapter` | `Framework/Http/HttpAdapter.php` | PSR-7 ↔ legacy konverzió (helper, nem middleware) |
| `ContentNegotiation` | `Framework/Http/ContentNegotiation.php` | JSON vs HTML választás (helper) |
