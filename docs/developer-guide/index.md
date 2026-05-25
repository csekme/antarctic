# Antarctic Developer Guide

Üdv az **Antarctic** keretrendszer fejlesztői dokumentációjában. Ez az útmutató azoknak szól, akik az Antarctic-ra építenek alkalmazást — nem azoknak, akik magát a keretrendszert fejlesztik.

!!! info "Élő dokumentáció"
    Ez a guide a keretrendszer **jelenlegi** állapotát tükrözi. Minden új funkció (új middleware, attribútum, CLI parancs, config kulcs) ide kerül a kapcsolódó PR-rel együtt.

## Az Antarctic röviden

Az Antarctic egy PHP 8.2+ minimal MVC framework, amely a **SPA-natív backend** szerepre van optimalizálva:

- **PSR-7 / PSR-15** HTTP réteg (middleware pipeline)
- **Attribútum-alapú routing** (`#[Path]`)
- **JSON-first** API endpointok `/api/v1/*` namespace alatt
- **RS256 JWT** autentikáció refresh token rotációval *(M2-ben épül)*
- **Twig** szerver oldali rendereléshez *(legacy, M2.d-ben kivezetve)*
- **Drop-in vagy separate** SPA deploy *(M3-ban)*

## Mit hol találsz

| Téma | Hol |
|---|---|
| Új projekt indítása | [Első lépések](getting-started.md) |
| Hogyan épül fel egy kérés útja | [Architektúra](architecture.md) |
| Middleware írása, sorrendje | [HTTP / Middleware](http/middleware.md) |
| Új végpont hozzáadása | [HTTP / Routing](http/routing.md) |
| CORS engedélyezése külön origin SPA-nak | [HTTP / CORS](http/cors.md) |
| Hibakezelés, problem+json | [HTTP / Hibakezelés](http/error-handling.md) |
| Config kulcsok, env változók | [Konfiguráció](configuration.md) |
| Unit / integration test írása | [Tesztelés](testing.md) |

## Verziók és milestone-ok

A keretrendszer fejlesztése milestone-okra van bontva (M0…M6). A milestone PR-ek konkrét tartalma a [docs/m{n}.md](../m1.md) fájlokban van részletezve.

| Milestone | Téma | Állapot |
|---|---|---|
| M0 | PHPStan + PHPUnit + Monolog + CI alap | ✅ kész |
| M1 | PSR-7/PSR-15 pipeline, CORS, RFC 7807 | ✅ kész |
| M2 | RS256 JWT auth + refresh rotation | 🚧 készülőben |
| M3 | Drop-in SPA + routing rewrite + DI | ⏳ tervezett |
| M4 | Migrations + validáció + OpenAPI | ⏳ tervezett |
| M5 | Production Docker + observability | ⏳ tervezett |
| M6 | Példa React SPA | ⏳ tervezett |

## Hozzájárulás a doksihoz

A guide a [`docs/developer-guide/`](https://github.com/csekme/antarctic/tree/main/docs/developer-guide) mappában él, plain Markdown forrással. A render motor [MkDocs Material](https://squidfunk.github.io/mkdocs-material/). Részletes hozzáírási útmutató: [Karbantartói útmutató](_meta/MAINTAINING.md).
