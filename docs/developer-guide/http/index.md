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

## Pipeline-sorrend (jelenlegi)

```text
ErrorHandlerMiddleware
   ↓
CorsMiddleware
   ↓
LegacyDispatcherMiddleware       (← itt fut a Dispatcher → Router → Controller)
   ↓
NotFoundHandler  (fallback)
```

A jövőbeli middleware-ek beillesztési pontjai:

- **SecurityHeaders** — HSTS, CSP, X-Frame-Options *(M5)*
- **RequestId** — `X-Request-Id` echo + log kontextus *(M1 jövőbeli kibővítés)*
- **RateLimit** — IP / user-alapú throttling *(M4)*
- **Auth** — Bearer JWT verifikáció *(M2.b)*
