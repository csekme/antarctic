# HTTP réteg

Az Antarctic HTTP rétege **PSR-7** (üzenetek) és **PSR-15** (middleware) alapokon nyugszik. Minden bejövő HTTP kérés egy lineáris middleware pipeline-on megy át, mielőtt eljut egy kontrollerhez.

## A réteg főszereplői

| Komponens | Mit csinál |
|---|---|
| [Middleware pipeline](middleware.md) | PSR-15 walker, ami sorban hívja a middleware-eket |
| [Routing](routing.md) | `#[Path]` attribútum-alapú URL ↔ kontroller match |
| [Request / Response](request-response.md) | PSR-7 alap, legacy `Framework\Request`/`Response` adapter |
| [CORS](cors.md) | Allow-list, preflight, cross-origin SPA támogatás |
| [Hibakezelés (RFC 7807)](error-handling.md) | Throwable → `application/problem+json` |
| [Validáció (Request DTO-k)](validation.md) | DTO hidratáció + `symfony/validator` → 422 problem+json `errors` |
| [OpenAPI + Swagger UI](openapi.md) | `zircote/swagger-php` scan → `/api/v1/docs.json` + Swagger UI dev-ben |
| [Pagination konvenció](pagination.md) | `?page=&perPage=&sort=&filter[]` query + `{data, meta}` envelope |
| [Rate limit](rate-limit.md) | PSR-15 throttling middleware, 429 + `Retry-After` + `X-RateLimit-*` |
| [Security headers](security-headers.md) | HSTS, CSP, X-Frame, Referrer-Policy, Permissions-Policy baseline |

## Pipeline-sorrend

```text
SecurityHeadersMiddleware
   ↓
TraceIdMiddleware                 (← X-Request-Id + Monolog extra.trace_id)
   ↓
ErrorHandlerMiddleware
   ↓
HttpsRedirectMiddleware            (← opcionális: APP_FORCE_HTTPS=1)
   ↓
CorsMiddleware
   ↓
RateLimitMiddleware                (← opcionális: APP_RATE_LIMIT=1; in-memory vagy Redis)
   ↓
AuthMiddleware                     (← JWT kulcs jelenlétén múlik)
   ↓
LegacyDispatcherMiddleware         (← itt fut a Dispatcher → Router → Controller)
   ↓
NotFoundHandler  (fallback)
```

A részletes sorrendi indokláshoz: [Middleware pipeline](middleware.md#pipeline-sorrend).
