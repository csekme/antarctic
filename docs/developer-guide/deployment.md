# Deployment és SPA üzemmódok

Az Antarctic ugyanazon kódbázis három deploy-szcenáriót támogat. A választást az `APP_SPA_MODE` env változó és az `src/html/app/index.html` fájl jelenléte együtt dönti el.

## A három mód

| Mód | `APP_SPA_MODE` | `src/html/app/index.html` | Mit szolgál a backend |
|---|---|---|---|
| **separate** | `separate` | hiányzik | Csak `/api/*`. Nem-API path → 404. |
| **embedded** | `embedded` | jelen | `/api/*` + `/app/*` + `/` → a SPA indexére fallback. |
| **both** | `both` | jelen | Mindkettő — fejlesztéshez. |

A **PHP réteg** ezt a `Framework\SpaMode::current()` enumon keresztül látja:

```php
use Framework\SpaMode;

$mode = SpaMode::current();
$mode->servesSpa();    // false separate-ben, true a többiben
$mode->requiresCors(); // true mindenhol, kivéve embedded
```

Az `APP_SPA_MODE` env változónak `getenv()`, `$_ENV` és `$_SERVER` mindhárom forrásból olvashatónak kell lennie — a `SpaMode::current()` mind a hármat megnézi, és ha egyik se ad értelmes értéket, `SEPARATE`-re esik vissza.

## Apache (`src/html/.htaccess`)

A `.htaccess` hat szabályt tartalmaz; lényege:

1. `/api/*` és `/healthz`, `/readyz` → backend (`index.php`).
2. `/app/*` alatt létező fájl → direktben.
3. `/app/*` alatt nem létező fájl → `app/index.html` (SPA history fallback).
4. Bármely más path + létező `app/index.html` → `app/index.html` (a SPA-nál a frontend kezeli a route-ot).
5. Egyébként → `index.php` (separate mód: backend dönt).

A módot **fájl-jelenlét** alapján váltja: ha létezik `app/index.html`, embedded; ha nem, separate. Ez azt jelenti, hogy ugyanaz a `.htaccess` mindkét módban használható — csak a deploy lépésnek kell eldöntenie, beleteszi-e a build outputot az `app/`-ba.

## Nginx (`docker/nginx/default.conf.example`)

A repo szállít egy production-ready Nginx vhost mintát PHP-FPM upstream-mel. Másold `docker/nginx/default.conf`-ra (vagy a `conf.d/` mappádba), és igazítsd:

- `server_name` — a deploy domain.
- `root` — a `src/html` abszolút útvonala a konténerben.
- `fastcgi_pass` — a PHP-FPM upstream címe.
- `map $host $app_spa_mode { ... }` — a mód értékének beállítása, ha vhost-szinten szeretnéd.

A `.htaccess`-hez hasonlóan a Nginx config is `try_files`-szal kezeli a SPA history fallback-et.

## Drop-in SPA build

A `src/html/app/` mappa verzionálva van, de a tartalma `gitignore`-olva (`/src/html/app/*`, kivéve `.gitkeep`). A SPA build deploy-folyamatának:

```bash
# Build a SPA forrásban
cd examples/react-spa
npm ci && npm run build

# Drop-in: a dist tartalma másolva a webrootba
cp -r dist/* ../../src/html/app/

# Apache vagy Nginx újraindítás nem kell — fájl-alapú a döntés.
```

CI-ban tipikusan a multi-stage Dockerfile teszi ezt — [Production stack](#production-stack-docker-compose-prod-yml).

## Separate mód CORS

`APP_SPA_MODE=separate` esetén a SPA külön origin-en fut (pl. `http://localhost:5173` Vite dev-en). A CORS allow-list-et a `config/cors.php`-ben kell beállítani — a [CORS doksi](http/cors.md) részletezi.

A `SpaMode::requiresCors()` igazat ad mindenre, kivéve `embedded` módra — a jövőbeli CORS-middleware ezzel automatikusan ki tud kapcsolni same-origin embedded deploy-ban (jelenleg manuális config).

## Production stack (`docker-compose.prod.yml`)

Az M5 óta a repo szállít egy multi-stage Docker image-et és egy production-ready compose fájlt. A stack 4 service-ből áll:

| Service | Image | Funkció |
|---|---|---|
| `php-fpm` | [docker/php-fpm/Dockerfile](https://github.com/csekme/antarctic/blob/main/docker/php-fpm/Dockerfile) | PHP-FPM + opcache + JIT, app source + route cache pre-built |
| `nginx` | [docker/nginx/Dockerfile](https://github.com/csekme/antarctic/blob/main/docker/nginx/Dockerfile) | Front controller + SPA fallback + FastCGI upstream |
| `db` | `postgres:16-alpine` | Doctrine migration-vezérelt séma |
| `redis` | `redis:7-alpine` | Shared rate-limit bucket store (multi-worker FPM-hez) |

### Build és indítás

```bash
DATABASE_PASSWORD=secret \
  docker compose -f docker-compose.prod.yml up -d --build

# Migrációk az új containerben:
docker exec antarctic-fpm vendor/bin/doctrine-migrations migrations:migrate --no-interaction

# Smoke test:
curl -fsS http://localhost/api/v1/healthz   # {"status":"ok"}
curl -fsS http://localhost/api/v1/readyz    # {"status":"ready","checks":{"database":"ok"}}
```

### Multi-stage build előnyei

A [Dockerfile](https://github.com/csekme/antarctic/blob/main/docker/php-fpm/Dockerfile) 3 stage-re bontja a buildet:

1. **`vendor`** — composer install `--no-dev`. Külön layer, csak akkor invalidálódik, ha `composer.json` / `composer.lock` változik.
2. **`build`** — app source copy + `bin/console route:cache` + `bin/console openapi:dump`. A runtime ezeket pre-built artefactként olvassa (nincs runtime reflection-scan).
3. **`runtime`** — `php:8.2-fpm-alpine` + pdo_pgsql + opcache. Nincs benne composer, sem build tool — kisebb image, kisebb support surface.

### Production env változók

A [.env.example](https://github.com/csekme/antarctic/blob/main/src/.env.example) M5 blokk a production-flag-eket dokumentálja. A leglényegesebbek:

| Env | Cél | Production érték |
|---|---|---|
| `APP_TRUST_PROXY` | `X-Forwarded-Proto` + `X-Forwarded-For` honorálás | `1` (TLS-terminating proxy mögött) |
| `APP_FORCE_HTTPS` | 301 plain HTTP → HTTPS | `1` |
| `APP_RATE_LIMIT` | rate-limit middleware master switch | `1` |
| `APP_RATE_LIMIT_BACKEND` | `memory` (single-worker) \| `redis` (multi-worker) | `redis` |
| `APP_LOG_LEVEL` | Monolog szint | `INFO` (vagy `WARNING` ha hangos) |
| `APP_DI_COMPILE` | php-di compilation cache | `1` |
| `APP_CSP` | Content-Security-Policy override | SPA host-spec |

### Healthcheck stratégia

A compose healthcheck-ek minden service-en végpontot pingelnek:

- `nginx` → `GET /api/v1/healthz` (liveness — process up).
- `php-fpm` → `fsockopen(127.0.0.1, 9000)` (FPM socket fogad).
- `db` → `pg_isready`.
- `redis` → `redis-cli ping`.

A k8s Pod-szinten a `/api/v1/healthz` (liveness) és `/api/v1/readyz` (readiness — DB ping is) endpoint-okat érdemes használni. A `HttpsRedirectMiddleware` excluded prefix listája ezt a két útvonalat plain HTTP-n is átengedi, hogy a k8s probe ne kerüljön redirektbe.

## Mit *nem* csinál még a backend

- **OpenTelemetry tracing**: jelenleg struktúrált JSON log + trace ID elég a 12-factor observability minimumhoz. OpenTelemetry SDK opcionális — külön PR.
- **`/` info endpoint**: jelenleg separate módban a `/` 404; nem irányítjuk `/api/v1/docs.json`-ra automatikusan.
- **phpredis adapter**: a `RedisLike` interface ext-redis-szel is kompatibilis, csak nem szállítjuk a default csomagban. ~30 LOC adapter, lásd `PredisAdapter` szerkezetét.
