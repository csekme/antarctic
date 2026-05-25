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

CI-ban tipikusan a multi-stage Dockerfile teszi ezt (M5 fogja szállítani).

## Separate mód CORS

`APP_SPA_MODE=separate` esetén a SPA külön origin-en fut (pl. `http://localhost:5173` Vite dev-en). A CORS allow-list-et a `config/cors.php`-ben kell beállítani — a [CORS doksi](http/cors.md) részletezi.

A `SpaMode::requiresCors()` igazat ad mindenre, kivéve `embedded` módra — a jövőbeli CORS-middleware ezzel automatikusan ki tud kapcsolni same-origin embedded deploy-ban (jelenleg manuális config).

## Mit *nem* csinál még a backend

- **`/healthz` és `/readyz` endpointok**: az M5-ben jönnek (Docker healthcheck + DB ping + JWT kulcs availability). Az `.htaccess` és Nginx már útba küldi őket az `index.php`-re, de a route még nem létezik (404).
- **`/` info endpoint**: jelenleg separate módban a `/` 404; nem irányítjuk `/api/v1/docs.json`-ra automatikusan.
- **Embedded mód CSP**: az M5 security-header middleware fogja a `Content-Security-Policy` headert hozzáadni.
