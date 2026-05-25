# Architektúra

Ez az oldal magas szinten mutatja, hogyan utazik egy HTTP kérés az Antarctic-on keresztül.

## A teljes kép

```
┌──────────────┐   HTTP    ┌─────────────────────┐
│  Kliens (SPA │ ────────▶ │  Apache / nginx     │
│  vagy curl)  │           │  + .htaccess        │
└──────────────┘           └──────────┬──────────┘
                                      │
                                      ▼
                          ┌───────────────────────┐
                          │  src/html/index.php   │
                          │  (front controller)   │
                          └──────────┬────────────┘
                                     ▼
                          ┌───────────────────────┐
                          │  Framework/Bootstrap  │
                          │  - session_start      │
                          │  - .env load          │
                          │  - DI container       │
                          │  - PSR-7 ServerReq    │
                          │  - Pipeline build     │
                          └──────────┬────────────┘
                                     ▼
                ┌────────────────────────────────────────┐
                │  PSR-15 Middleware Pipeline             │
                │                                         │
                │  ErrorHandlerMiddleware                 │
                │     │                                   │
                │     ▼                                   │
                │  CorsMiddleware                         │
                │     │                                   │
                │     ▼                                   │
                │  LegacyDispatcherMiddleware             │
                │     │                                   │
                │     ▼                                   │
                │  (jövőben: Auth, RateLimit, …)          │
                │     │                                   │
                │     ▼                                   │
                │  NotFoundHandler (fallback)             │
                └─────────────────┬──────────────────────┘
                                  ▼
                       ┌────────────────────┐
                       │  Laminas           │
                       │  SapiEmitter       │
                       └──────────┬─────────┘
                                  ▼
                              HTTP válasz
```

## A kérés útja lépésről lépésre

### 1. Web szerver és .htaccess

Apache (vagy nginx) a `src/html/` mappát szolgálja ki. A `.htaccess` átírja a nem-fájl kéréseket az `index.php`-re, megőrizve a query stringet:

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-l
RewriteRule ^(.*)$ index.php?$1 [L,QSA]
```

Így a `/api/v1/hello` kérés `index.php?api/v1/hello` lesz a PHP felé. A `$_SERVER['QUERY_STRING']` ezt a stringet tartalmazza, és a Router ezen match-el.

### 2. Front controller — `src/html/index.php`

Minimális:

```php
define("ROOT_PATH", dirname(__DIR__));
require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/Framework/Bootstrap.php';
```

Csak betölti a Composer autoloadert és a Bootstrap-et.

### 3. Bootstrap — `src/Framework/Bootstrap.php`

A `Bootstrap.php` egyetlen procedurális script, ami:

1. Setupol PHP error reporting + `set_error_handler`-t.
2. Indít session-t (`session_start()`).
3. Betölti a `.env`-et.
4. Példányosítja a `Container`, `Router`, `Dispatcher`-t.
5. **PSR-7 `ServerRequestInterface`-et épít** a globálokból (`nyholm/psr7-server`).
6. **PSR-15 middleware pipeline-t épít** a `MiddlewarePipeline` osztállyal.
7. Lefuttatja a pipeline-t: `$pipeline->handle($request)`.
8. **Emit-eli** a választ a `SapiEmitter` segítségével.

### 4. Middleware pipeline

A pipeline szerver-felöl (külső) befelé halad. Minden middleware:

- Megnézi / módosíthatja a requestet.
- Továbbadja a következő middleware-nek (`$handler->handle($req)`).
- Megnézi / módosíthatja a visszakapott választ.
- Visszaadja a hívónak.

Részletek: [HTTP / Middleware](http/middleware.md).

### 5. Legacy dispatch

A pipeline jelenleg utolsó middleware-je a `LegacyDispatcherMiddleware`. Ez:

1. Átalakítja a PSR-7 requestet `Framework\Request` objektummá (`HttpAdapter::toLegacyRequest`).
2. Meghívja a régi `Dispatcher::handleRequest($legacyRequest)`-et, ami:
   - Routerrel matchel.
   - CSRF-et ellenőriz (POST/PUT/PATCH/DELETE-nél).
   - Példányosítja a kontrollert.
   - Lefuttatja az interceptorokat (before).
   - Meghívja a kontroller action metódusát.
   - Lefuttatja az interceptorokat (after).
   - Visszaad egy `Framework\Response`-t.
3. A legacy Response-t visszaalakítja PSR-7 `ResponseInterface`-szé.

!!! note "Várható változás (M2-M3)"
    A `LegacyDispatcherMiddleware` átmeneti megoldás. A teljes PSR-15 átállás után a kontrollerek közvetlenül PSR-7-tel fognak dolgozni, és a Dispatcher feloldódik egy `RouterMiddleware + ControllerDispatcherMiddleware` páros mögött.

### 6. Emit

A `Laminas\HttpHandlerRunner\Emitter\SapiEmitter` kiküldi a PSR-7 választ a kliensnek (státuszkód, headerek, body).

## Komponensek térképe

| Komponens | Fájl | Szerepe |
|---|---|---|
| Front controller | `src/html/index.php` | Bootstrap betöltés |
| Bootstrap | `src/Framework/Bootstrap.php` | Pipeline összeállítás |
| Pipeline | `src/Framework/Http/MiddlewarePipeline.php` | PSR-15 walker |
| HTTP adapter | `src/Framework/Http/HttpAdapter.php` | PSR-7 ↔ legacy Request/Response |
| Error boundary | `src/Framework/Http/ErrorHandlerMiddleware.php` | Throwable → response |
| CORS | `src/Framework/Http/CorsMiddleware.php` | Allow-list + preflight |
| Legacy dispatcher | `src/Framework/Http/LegacyDispatcherMiddleware.php` | Wrap a régi Dispatcher-ön |
| Router | `src/Framework/Routing/StandardRouterImpl.php` | Attribútum-alapú URL match |
| Container | `src/Framework/Container.php` | Egyszerű DI (M3.c-ben php-di) |
| ErrorHandler (classic) | `src/Framework/ErrorHandler.php` | PHP warning → exception |
