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

A pipeline összeállítása a `Bootstrap.php`-ban történik. A teljes production-ready sorrend:

```php
$middlewares = [
    new SecurityHeadersMiddleware($securityHeadersConfig),
    new TraceIdMiddleware(),
    new ErrorHandlerMiddleware(debug: $debug, logger: $logger),
];
if ($forceHttps) {
    $middlewares[] = new HttpsRedirectMiddleware(trustProxy: $trustProxy, excludedPrefixes: ['/api/v1/healthz', '/api/v1/readyz']);
}
$middlewares[] = new CorsMiddleware($corsConfig);
if ($rateLimitEnabled) {
    $middlewares[] = new RateLimitMiddleware(...);
}
$middlewares[] = new AuthMiddleware($tokenService);
$middlewares[] = new LegacyDispatcherMiddleware($dispatcher);

$pipeline = new MiddlewarePipeline($middlewares, new NotFoundHandler());
(new SapiEmitter())->emit($pipeline->handle($request));
```

## Pipeline-sorrend

A sorrend **számít**, mert minden middleware egyaránt láthatja a beérkező requestet ÉS a kimenő választ:

- A pipeline elején lévő middleware **legkorábban látja a requestet** és **legkésőbb látja a választ**.
- A pipeline végén lévő ennek a fordítottja.

```text
Request  ──▶ SecurityHeaders ──▶ TraceId ──▶ ErrorHandler ──▶ [HttpsRedirect] ──▶ Cors ──▶ [RateLimit] ──▶ [Auth] ──▶ LegacyDispatcher ──▶ NotFound
                                                                                                                                              │
Response ◀── SecurityHeaders ◀── TraceId ◀── ErrorHandler ◀── [HttpsRedirect] ◀── Cors ◀── [RateLimit] ◀── [Auth] ◀── LegacyDispatcher ◀──────┘
```

A szögletes zárójelben lévők env-flag mögött opcionálisak (`APP_FORCE_HTTPS`, `APP_RATE_LIMIT`, JWT-kulcs jelenléte).

**Hüvelykujj-szabály**:

- A **security header**-eket legkívülre — minden response (még az 5xx error is) megkapja a baseline-t.
- A **trace ID** az `ErrorHandler` ELŐTT — különben a `Throwable` log record-ja kimaradna a `trace_id`-ből.
- A **hibakezelőnek** elöl kell lennie (downstream Throwable-öket be tud kapni).
- A **CORS** a hibakezelő után, hogy a hibaválaszok is megkapják a CORS headereket.
- Az **authentikáció** a routing UTÁN logikailag, de itt a pipeline szintjén előtte van — a `#[RequireAuth]` attribútum-vizsgálat a dispatcherben fut.
- A **rate-limit** a CORS UTÁN, hogy a preflight `OPTIONS` ne számítson bele a bucketbe.

## Új middleware regisztrálása

A middleware-eket kézzel rakod a Bootstrap pipeline listájába — döntsd el milyen sorrendben kell befonni a saját middleware-edet a build-in sorrendbe:

```php
// Bootstrap.php
$middlewares[] = new \Application\Http\Middleware\RequestLoggerMiddleware($logger);
$middlewares[] = new LegacyDispatcherMiddleware($dispatcher);

$pipeline = new MiddlewarePipeline($middlewares, new NotFoundHandler());
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
| `SecurityHeadersMiddleware` | `Framework/Http/SecurityHeadersMiddleware.php` | HSTS / CSP / X-Frame / Referrer-Policy baseline — lásd [Security headers](security-headers.md) |
| `TraceIdMiddleware` | `Framework/Http/TraceIdMiddleware.php` | Per-request korrelációs ID — lásd [Observability](../observability.md) |
| `ErrorHandlerMiddleware` | `Framework/Http/ErrorHandlerMiddleware.php` | Throwable → response, RFC 7807 vagy HTML |
| `HttpsRedirectMiddleware` | `Framework/Http/HttpsRedirectMiddleware.php` | Opcionális 301 HTTP → HTTPS, proxy-aware |
| `CorsMiddleware` | `Framework/Http/CorsMiddleware.php` | CORS allow-list + preflight |
| `RateLimitMiddleware` | `Framework/Http/RateLimit/RateLimitMiddleware.php` | Path-prefix bucket-ek, in-memory vagy Redis store |
| `AuthMiddleware` | `Framework/Auth/AuthMiddleware.php` | JWT Bearer-token + `authUser` request attribútum |
| `LegacyDispatcherMiddleware` | `Framework/Http/LegacyDispatcherMiddleware.php` | Wrap a régi `Dispatcher`-en |
| `NotFoundHandler` | `Framework/Http/NotFoundHandler.php` | Terminális 404 fallback |
| `HttpAdapter` | `Framework/Http/HttpAdapter.php` | PSR-7 ↔ legacy konverzió (helper, nem middleware) |
| `ContentNegotiation` | `Framework/Http/ContentNegotiation.php` | JSON vs HTML választás (helper) |
| `RequestScheme` | `Framework/Http/RequestScheme.php` | `isHttps()` helper proxy-aware logikával (helper) |
