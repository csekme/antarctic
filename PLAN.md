# Antarctic — Production-ready, SPA-barát fejlesztési terv

Cél: olyan PHP backend, amely **production környezetben** stabilan kiszolgálja a külön deploy-olt **SPA frontendet** (React / Vue / Svelte / akármi).

Hat fázis, mindegyik önállóan release-elhető. A milestone-okhoz tartozó konkrét PR-sorrend a végén.

---

## M0 — Higiénia (1–2 nap)

Alap minőségbiztosítás, mielőtt bármi újat építünk.

- [ ] **PHPStan** (level 5 → ratchet up) + **Rector** (PHP 8.2 ruleset). Baseline file a meglévő hibákra.
- [ ] **`vlucas/phpdotenv`** — a custom [Dotenv.php](src/Framework/Dotenv.php) kivezetése, validációval (kötelező változók megléte).
- [ ] **Secret kezelés**: `secretKey`, DB credentials a JSON configból `.env`-be (`APP_SECRET_KEY`, `DB_*`). Backward-compat fallback átmenetileg.
- [ ] **PHPUnit 11** + alap test bootstrap, két-három smoke teszt (`Container`, `Token`, `Response`).
- [ ] **GitHub Actions CI**: composer install + lint + phpstan + phpunit.
- [ ] **PSR-3 logging** ([monolog/monolog](https://github.com/Seldaek/monolog)) — `error_log` és `echo` cserélve.
- [ ] **Branch policy**: `feature_auto_routing` lezárása.

---

## M1 — SPA-kompatibilis HTTP réteg (3–5 nap)

**Ez a legfontosabb a célhoz.** Itt nyílik meg a backend egy külön origin-ű SPA-nak.

- [ ] **CORS middleware** allow-list konfigurációval (`config/cors.php`: origins, methods, headers, credentials, max-age). OPTIONS preflight short-circuit a Dispatcher elé.
- [ ] **Tiszta API útvonal namespace** (pl. `/api/v1/*`) — ezekre **nincs** Twig CSRF-injekció, **nincs** session redirect, csak JSON in/out.
- [ ] **Dual auth**:
  - session marad a beépített kontrollereknek;
  - SPA-knak **bearer token** (opaque token, DB-ben tárolt + hash; később JWT, ha kell).
  - `Authorization: Bearer …` header parsing a `Request`-ben.
- [ ] **CSRF mód-szelektív**: csak session-alapú útvonalakon. Bearer tokenes kérés CSRF-mentes (best practice).
- [ ] **Tartalom-egyeztetés a hibakezelőben** — `Accept: application/json` → JSON error envelope `{error: {code, message, details}}`; egyébként HTML.
- [ ] **`Request` / `Response` PSR-7-re cserélése** (`nyholm/psr7` + `laminas/laminas-httphandlerrunner`). A `ResponseBuilder` marad facade-ként.
- [ ] **Cookie flags fix**: `HttpOnly`, `Secure`, `SameSite=Lax|Strict` az auth cookie-knál.

---

## M2 — Routing & DI újraépítés (3–5 nap)

- [ ] **Route cache**: `ClassExploder` és reflection csak `bin/console route:cache`-kor fut, production runtime egyetlen `var/cache/routes.php` PHP fájlt require-öl. Dev módban opcionális auto-rebuild fájl-mtime alapján.
- [ ] **Method-aware match**: a `Router::match()` (URL, method) → params; külön nyilvántartás per HTTP method, hogy **405**-öt is tudjon adni 404 helyett.
- [ ] **`ClassExploder` reflection-alapúra** (`composer dump-autoload` osztálylista + `ReflectionClass::getAttributes()`), regex eltüntetve.
- [ ] **Több `#[Path]` egy osztályon**, csoport prefixek, opcionális route name (URL generáláshoz).
- [ ] **PSR-11 container csere** — [php-di/php-di](https://php-di.org) (autowire, singleton-cache, factory). Saját `Container.php` törölhető.
- [ ] **Kontroller resolve a containerből** — jelenleg `new $controller($params)`, így a kontroller nem kaphat injektált service-t.
- [ ] **PSR-15 middleware pipeline** — az `InterceptorInterface` PSR-15-tel kompatibilis lesz (vagy adapter). CORS, CSRF, Auth, RateLimit, Logging mind middleware-ré válik a Dispatcher hardkód helyett.

---

## M3 — Adatbázis & validáció (3–4 nap)

- [ ] **Migrációs eszköz**: [phinx](https://phinx.org) vagy `doctrine/migrations`. Külön `Application/Migrations/`.
- [ ] **Repository réteg a static `Dal::connection()` mögé** — PDO-t a containerből kapja, `AbstractUser::findByEmail` típusú static metódusok átkerülnek `UserRepository`-ba (tesztelhető).
- [ ] **Request validation**: `respect/validation` vagy `symfony/validator`. Attribútum-alapú DTO validáció: `#[Required]`, `#[Email]`, kontroller method paraméter automatikus hidratálás `Request::getJson()`-ból.
- [ ] **Probléma+JSON hibaformátum** ([RFC 7807](https://datatracker.ietf.org/doc/html/rfc7807)) konzisztens API hibákhoz — SPA kliensek így könnyen mappelnek.

---

## M4 — SPA-integráció finomságok (2–3 nap)

- [ ] **OpenAPI generátor** (`zircote/swagger-php`) az API kontrollerekből — `/api/docs.json` + Swagger UI dev-en. SPA TypeScript klienst generálhat belőle (openapi-typescript).
- [ ] **Pagination, sorting, filter konvenció** (`?page=1&perPage=20&sort=-createdAt&filter[status]=active`) helper + standard meta envelope.
- [ ] **Fájl feltöltés** multipart + presigned URL minta (S3-kompatibilis tárhoz, ha kell).
- [ ] **Rate limit middleware** (Redis vagy session-store backed).
- [ ] **WebSocket / SSE opcionális** (long polling fallback elég lehet kezdetnek).
- [ ] **Frontend dev-proxy receptúra**: Vite/Next.js proxy a `/api`-ra docker hálózaton — README minta config.

---

## M5 — Production deploy & megfigyelhetőség (2–3 nap)

- [ ] **Docker production image**: PHP-FPM + nginx multi-stage build, opcache + JIT engedélyezve, route cache + `composer install --no-dev` az image-ben.
- [ ] **Healthcheck endpoint** (`/healthz`, `/readyz`) — DB ping is.
- [ ] **Strukturált log (JSON)** stdout-ra (12-factor), trace ID minden requesthez.
- [ ] **Security header middleware**: HSTS, X-Frame-Options, X-Content-Type-Options, CSP (SPA-host-spec).
- [ ] **Secret kezelés**: `.env` csak dev-ben, prod-ban env változó / Docker secret.
- [ ] **HTTPS enforcement** (proxy-aware `X-Forwarded-Proto`).
- [ ] **Observability**: opcionális OpenTelemetry PHP SDK, alapból elég a structured log + uptime probe.

---

## PR-sorrend

| # | Fázis | Tartalom | Kockázat |
|---|---|---|---|
| 0 | patch | A két azonnali bug fix ([Dispatcher.php:93](src/Framework/Dispatcher.php#L93), [Controller.php:35](src/Framework/Controller.php#L35)) — 0.9.0-alpha.5 | 0 |
| 1 | M0 | phpstan + phpunit + monolog + dotenv + CI | 0 |
| 2 | M1.a | CORS + tartalom-egyeztetett hibakezelő | alacsony |
| 3 | M1.b | Bearer token auth + API namespace (`/api/v1/*`) | közepes |
| 4 | M2.a | Route cache + method-aware match | alacsony |
| 5 | M2.b | Reflection-alapú ClassExploder + több `#[Path]` | közepes |
| 6 | M2.c | PSR-11 + PSR-15 (Container + Middleware csere) | **magas** |
| 7 | M3.a | Migrations + Repository réteg | közepes |
| 8 | M3.b | Request validation + RFC 7807 | alacsony |
| 9 | M4 | OpenAPI + pagination + rate limit | alacsony |
| 10 | M5 | Production image + observability | közepes |

**Vágási elv**: minden PR egy gondolat. Ha egy PR > ~500 sornyi diff, kettébontás.

---

## Definíció: „SPA-barát"

A roadmap végén az alábbi minimum garantált a backend felé:

1. **CORS** allow-list konfigurálva, preflight OK.
2. **Bearer token auth**: SPA login → `POST /api/v1/auth/login` → `{token, expiresAt}`.
3. **Stateless API kérés**: `Authorization: Bearer …` header, nincs cookie szükséges.
4. **JSON-only error envelope** RFC 7807 formátumban.
5. **OpenAPI 3.1 spec** generáltan a `/api/docs.json` alól, SPA TypeScript klienst belőle generálhat.
6. **Predictable pagination**: `{data: […], meta: {page, perPage, total}}`.
7. **`/healthz`** + `/readyz` deploy-ready.
8. **Strukturált JSON log** stdout-ra, trace ID-vel.

## Definíció: „Production-ready"

1. PHP-FPM + nginx + opcache + JIT, route cache pre-built az image-be.
2. `composer install --no-dev` az image-ben.
3. Secret env változóból (nem fájlból).
4. HSTS + CSP + standard security header set.
5. PHPStan level 6 zöld, PHPUnit ≥ 60% line coverage a `Framework/` namespace-en.
6. CI minden PR-en zöld.
