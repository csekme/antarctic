# Antarctic Developer Guide

Üdv az **Antarctic** keretrendszer fejlesztői dokumentációjában. Ez az útmutató azoknak szól, akik az Antarctic-ra építenek alkalmazást — nem azoknak, akik magát a keretrendszert fejlesztik.

!!! info "Élő dokumentáció"
    Ez a guide a keretrendszer **jelenlegi** állapotát tükrözi. Minden új funkció (új middleware, attribútum, CLI parancs, config kulcs) ide kerül a kapcsolódó PR-rel együtt.

## Az Antarctic röviden

Az Antarctic egy PHP 8.2+ minimal framework, amely a **SPA-natív backend** szerepre van optimalizálva:

- **PSR-7 / PSR-15** HTTP réteg (middleware pipeline)
- **Attribútum-alapú routing** (`#[Path]`)
- **JSON-first** API endpointok `/api/v1/*` namespace alatt
- **RS256 JWT** autentikáció refresh token rotációval és 2FA-val
- **Drop-in vagy separate** SPA deploy (`APP_SPA_MODE`)
- **PSR-11 container** (php-di) autowire-ral, attribútum-DI támogatással
- **Doctrine migrations + Repository** réteg, dual-database (MariaDB / PostgreSQL)
- **DTO validáció** (symfony/validator) 422 problem+json válaszokkal
- **OpenAPI 3.1** + Swagger UI (`zircote/swagger-php`)
- **Rate limit middleware** in-memory és Redis backend-del
- **Production hardening** — security headers, trace ID, JSON log, proxy-aware HTTPS

## Mit hol találsz

| Téma | Hol |
|---|---|
| Új projekt indítása | [Első lépések](getting-started.md) |
| Hogyan épül fel egy kérés útja | [Architektúra](architecture.md) |
| Middleware írása, sorrendje | [HTTP / Middleware](http/middleware.md) |
| Új végpont hozzáadása | [HTTP / Routing](http/routing.md) |
| CORS engedélyezése külön origin SPA-nak | [HTTP / CORS](http/cors.md) |
| Hibakezelés, problem+json | [HTTP / Hibakezelés](http/error-handling.md) |
| Request DTO + validáció | [HTTP / Validáció](http/validation.md) |
| OpenAPI spec + Swagger UI | [HTTP / OpenAPI](http/openapi.md) |
| Pagination konvenció | [HTTP / Pagination](http/pagination.md) |
| Rate limit middleware | [HTTP / Rate limit](http/rate-limit.md) |
| Config kulcsok, env változók | [Konfiguráció](configuration.md) |
| Unit / integration test írása | [Tesztelés](testing.md) |

## Verziók és változástörténet

Az aktuális stabil verzió a **1.0.0**. A részletes változástörténet a repo gyökerében található [`CHANGELOG.md`](../../CHANGELOG.md) fájlban. A v1.0.0-ig vezető fejlesztési mérföldkövek belső jegyzőkönyvei a [`docs/m1.md` … `docs/m6.md`](../m1.md) fájlokban vannak.

## Hozzájárulás a doksihoz

A guide a [`docs/developer-guide/`](https://github.com/csekme/antarctic/tree/main/docs/developer-guide) mappában él, plain Markdown forrással. A render motor [MkDocs Material](https://squidfunk.github.io/mkdocs-material/). Részletes hozzáírási útmutató: [Karbantartói útmutató](_meta/MAINTAINING.md).
