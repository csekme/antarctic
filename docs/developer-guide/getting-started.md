# Első lépések

Ez a fejezet végigvezet egy teljes Antarctic-alapú alkalmazás felállításán: a backend Docker-stack-jén, a `.env` konfiguráción, az első saját végponton, a mellékelt React SPA-n, a register → email-verify → login → 2FA flow-n, és a leggyakoribb buktatók (port-mismatch, host vs container DNS, `mariadb:` DSN) elkerülésén.

## Mit fogsz építeni

A végén lesz egy futó Antarctic backend a `http://localhost`-on, egy hozzákapcsolódó React SPA dev szerver a `http://localhost:5173`-en, és egy regisztrált, 2FA-val védett user account. Innen kezdheted a saját kontrollereidet, modelljeidet és UI-odat hozzáadni.

## Előfeltételek

- **Docker + Docker Compose** — a backend + DB konténer-stackhez.
- **PHP 8.4+** *(csak ha host-gépen is futtatsz CLI eszközöket: Composer, PHPUnit, Doctrine Migrations)*. A konténer maga is PHP 8.4-en megy.
- **Composer** — host-on a `composer install`-hoz.
- **Node.js 20+** — a React SPA example fut a Vite-on.
- **openssl** vagy más random-hex generátor — az `APP_SECRET_KEY`-hez.

## A repo szerkezete

```
antarctic/
├── docker/                  # Apache (PHP 8.4) + MariaDB/PostgreSQL Dockerfile-ok
├── docs/                    # Ez a guide (MkDocs Material)
├── examples/
│   └── react-spa/           # Vite + React + TS minta SPA
├── src/
│   ├── Application/         # ⚠️ A TE alkalmazásod (gitignored a Controllers/View kivételével)
│   │   └── Dto/             # A keretrendszerhez tartozó alap-DTO-k
│   ├── Framework/           # Maga a keretrendszer (kontroller, router, auth, …)
│   ├── config/              # Konfig PHP fájlok (cors, rate-limit, jwt, security-headers)
│   ├── html/                # Webroot (index.php + .htaccess)
│   ├── tests/               # Framework tesztek
│   ├── composer.json
│   ├── phpstan.neon.dist
│   └── phpunit.xml.dist
├── db/migrations/           # Doctrine Migrations osztályok
├── docker-compose.yml
└── mkdocs.yml
```

A saját kontrollereid az `src/Application/Controllers/` alá kerülnek, a model/DTO osztályok az `src/Application/` mappa többi részébe. Ezt a mappát te git-eled a saját projekt-repodban.

---

## 0. lépés — Repo klónozása és image build

```bash
git clone https://github.com/csekme/antarctic.git
cd antarctic

# A docker-compose build elindítja az apache+php-8.4-bookworm image-et
# (+ az adatbázis image-et: MariaDB alapból, ld. `.env`).
docker compose build
```

Ha az image build elszáll az `imagick` pecl-step-en, válts át az `mlocati/install-php-extensions`-os install-php-extensions imagick sorra a `docker/apache/Dockerfile`-ban — PHP 8.4 alatt az upstream `pecl install imagick` még nem mindenhol fordul.

---

## 1. lépés — `.env` konfigurálás (gyökér + `src/`)

**Két `.env` van:** a repo gyökerében lévőt a docker-compose olvassa (image-választás, DB engine, port), a `src/.env`-et a PHP runtime tölti be a `Framework\Dotenv`-vel. Mindkettőre szükség van.

### Gyökér `.env`

```bash
cp .env.example .env
```

Default értékek megfelelőek MariaDB-vel; PostgreSQL-re válts ha akarsz (`DATABASE=postgresql` + a `DATABASE_PORT`/`DATABASE_DATADIR` cseréje a kommentek szerint).

### `src/.env`

```bash
cp src/.env.example src/.env
```

A **kritikus** mezők, amiket beállíts (a többit hagyhatod default-on):

```bash
# Container-belül a docker-compose service-nevét használd!
# A 127.0.0.1 a konténer SAJÁT loopback-je, ahol nincs DB.
DATABASE=mariadb
DATABASE_HOST=database          # ← NE localhost, NE 127.0.0.1
DATABASE_USER=username
DATABASE_PASSWORD=password
DATABASE_NAME=database_name
DATABASE_PORT=3306

# HMAC secret a Token osztálynak (activation_hash, refresh-token hash, stb.).
# Egyszer beállítod, soha ne forgasd futó rendszeren — minden tárolt hash invalid lesz tőle.
APP_SECRET_KEY=               # ← generálj: openssl rand -hex 32

# Az SPA verify-email page URL-je. A backend a register-emailbe és (dev-flag-gel)
# a register response `verification_link` mezőjébe ezt rakja `?token=…` query-vel.
APP_VERIFY_EMAIL_URL=http://localhost:5173/verify-email

# Dev-only kényelmi flag: ha 1, a register response body-ja tartalmazza a
# `verification_link`-et — így SMTP nélkül is végigvihető a teljes flow.
# Production-ben HAGYD 0-n, különben a link megjelenik az API-on.
APP_EXPOSE_VERIFICATION_LINK=1

# Local dev-mode: lazítja a Secure cookie flaget, hogy HTTP-n is fusson.
APP_ENV=local
```

Generáld le a secretet és illeszd be:

```bash
echo "APP_SECRET_KEY=$(openssl rand -hex 32)" >> src/.env
# (Vagy nyisd meg szerkesztőben és pasteld a `openssl rand -hex 32` kimenetét.)
```

---

## 2. lépés — Backend indítás + DB migráció

```bash
docker compose up -d
```

A backend a `http://localhost/`-on hallgat (Apache port 80), az adatbázis a `database` service-en (compose hálózat), és port-forwardolva a host 3306-os porton is.

Migráld a sémát (host-on futó CLI, port-forward 3306-on át — itt **127.0.0.1**-et kell használni, mert a host gépről nézünk, nem a container belsejéből):

```bash
cd src
composer install                    # csak első alkalommal
DATABASE_HOST=127.0.0.1 \
  vendor/bin/doctrine-migrations migrate --no-interaction
cd ..
```

> **Miért két különböző DATABASE_HOST?** A container belül `database` (compose DNS), a host CLI-ből `127.0.0.1` (port-forward). Ld. a [Hibaelhárítás](#hibaelharitas) szekciót.

Sanity check:

```bash
curl -i http://localhost/api/v1/healthz
# HTTP/1.1 200 OK
# Content-Type: application/json
# {"status":"ok","time":"…"}
```

Ha 200 jött, a backend rendben van.

---

## 3. lépés — Az első saját végpont

Hozz létre egy `src/Application/Controllers/HelloController.php`-t:

```php
<?php
declare(strict_types=1);

namespace Application\Controllers;

use Framework\AbstractController;
use Framework\Path;
use Framework\Response;

class HelloController extends AbstractController
{
    #[Path(path: '/api/v1/hello', method: 'GET')]
    public function index(): Response
    {
        return Response::json([
            'message' => 'Hello from Antarctic',
            'time' => date(DATE_ATOM),
        ]);
    }
}
```

Próbáld ki:

```bash
curl http://localhost/api/v1/hello
# → {"message":"Hello from Antarctic","time":"2026-05-31T08:00:00+02:00"}
```

A `#[Path]` attribútumot a `Framework\Routing\StandardRouterImpl::discoverRoutes` reflection-nel olvassa minden dev-request-en. Production-ben warmold a route-cache-t (ld. [Routing](http/routing.md)).

### Mi történt a háttérben?

1. Az `html/index.php` betöltötte a `Bootstrap.php`-t.
2. A `Bootstrap` épített egy PSR-15 middleware pipeline-t: `SecurityHeaders → TraceId → ErrorHandler → Cors → RateLimit → Auth → LegacyDispatcher`.
3. A `ClassExploder` scannelte az `Application\Controllers` és `Framework\Controllers` namespace-ek `#[Path]` attribútumait, és felépítette a routing táblát.
4. A `LegacyDispatcherMiddleware` átalakította a PSR-7 requestet legacy `Request`-té, lefuttatta a kontrollert, és a Response-t visszaalakította PSR-7-re.
5. A `SapiEmitter` kiküldte a választ a kliensnek.

A részletekért lásd [Architektúra](architecture.md) és [HTTP / Middleware](http/middleware.md).

---

## 4. lépés — Példa React SPA indítása

A `examples/react-spa/` egy minimal Vite + React + TS app, ami a backend auth-API-jával beszélget. Ez a leggyorsabb módja annak, hogy a teljes register/verify/login/2FA flow-t végigjátszd anélkül, hogy saját SPA-t írnál.

```bash
cd examples/react-spa
cp .env.example .env
npm install
```

Nyisd meg a `.env`-et és ellenőrizd:

```bash
VITE_API_BASE=                         # üres = same-origin (proxy)
APP_BACKEND_ORIGIN=http://localhost    # ← port 80, NEM 8080
```

> **Miért nem 8080?** A docker-compose apache image a 80-as portra mappel — ez egy gyakori félreértés. A `vite.config.ts` proxy-zik `/api/v1/*`-ot ide.

Indítás:

```bash
npm run dev
```

Megnyit egy Vite dev szervert a `http://localhost:5173`-en. Ide menj a böngészőben — **NEM a backendre 8080-on**.

---

## 5. lépés — Teljes auth flow végigjátszása

A SPA kezdőképernyőjén:

### a) Regisztráció

1. Kattints **Create account**-ra.
2. Töltsd ki: email, username (min 3 karakter), jelszó (min 8), confirm.
3. Submit.

A backend létrehoz egy `is_active=0` user-t, és ha az `APP_EXPOSE_VERIFICATION_LINK=1` flag aktív, a sárga "Dev mode" banneren megkapod a verifikációs linket:

> Dev mode: the backend exposed the verification link directly. **Verify now**.

Production-ben ez a link csak emailen át jönne — SMTP-konfigot a `Application/application.json` `smtp` szekciója adja (`enabled: true` + host/port/credentials).

### b) Email-verifikáció

Kattints a "Verify now"-on. Ez `POST /api/v1/auth/verify-email` hívás, ami a `Token` HMAC-jét összeveti a DB-ben tárolt `activation_hash`-szel, és `is_active=1`-re állítja a user-t.

A SPA "Email verified" képernyőre kerül.

### c) Bejelentkezés

Kattints **Sign in**, töltsd ki az adatokat. Sikerre `/profile`-ra kerülsz.

> Ha a verify lépést kihagynád és próbálnál bejelentkezni: a backend `403 email_not_verified`-et ad, és a SPA célzott üzenetet mutat. Ez nem `401 invalid_credentials`, hogy a user lássa: a jelszava jó, csak még nem aktivált.

### d) 2FA bekapcsolása

`/profile` → **Enable 2FA**:

1. A backend generál egy TOTP secretet és visszaad egy QR-data-URI-t + otpauth URI-t.
2. Olvasd be a QR-kódot az authenticator app-pel (Google Authenticator, 1Password, Authy, …) vagy paszteld be a secretet kézzel.
3. Írd be a 6-jegyű kódot, **Confirm and enable**.

A 2FA mostantól aktív. Logout → újra login → most a backend egy challenge_token-t ad vissza, és a SPA a 2FA-verify formra vált.

### e) 2FA kikapcsolása

`/profile` → **Disable 2FA**: jelszó re-auth + DB-ben `enabled=0`. A SPA `refreshUser()`-rel frissíti a state-et.

---

## Hibaelhárítás {#hibaelharitas}

A mai dev-iterációban felmerült leggyakoribb gondok és a fixek:

### "ERR_CONNECTION_REFUSED" a böngészőben (`localhost:8080`)

A backend a **80-as porton** van (Apache image), nem a 8080-on. A SPA-t a `http://localhost:5173`-en nyisd, az API-t a `http://localhost`-on (port 80). A `examples/react-spa/.env`-ben `APP_BACKEND_ORIGIN=http://localhost` legyen.

### `Composer detected issues … require a PHP version ">= 8.4.0". You are running 8.2.29.`

A `composer.lock` Symfony 8.x-szel van rögzítve, ami PHP 8.4-et követel. A `docker/apache/Dockerfile` `FROM php:8.4-apache-bookworm`-ot kell, hogy használjon. Rebuild: `docker compose down && docker compose build --no-cache app && docker compose up -d`.

### `SQLSTATE[HY000] [2002] Connection refused` (`mysql:host=127.0.0.1`)

A container belsejéből a `127.0.0.1` a saját loopback-je, ahol nincs DB. A `src/.env`-ben `DATABASE_HOST=database` (a compose service-neve) legyen.

A host-gépről futó CLI eszközök (doctrine migrate, phpunit) viszont **127.0.0.1**-et használnak (a port-forward jól van), ezért egy soros override:

```bash
DATABASE_HOST=127.0.0.1 vendor/bin/doctrine-migrations migrate
```

### `could not find driver` a connection-nél

A PDO `mariadb:host=…` DSN prefix **nem létezik** — a `pdo_mysql` driver MariaDB-vel kompatibilis, és a `mysql:` prefixet várja. Az `Dal::getDbHost()` ezt már `mysql:`-re mappeli mind `DATABASE=mariadb`, mind `DATABASE=mysql` esetén.

### `Warning: The use statement with non-compound name 'X' has no effect`

A `Bootstrap.php` global namespace-ben van — itt a `use \Foo;`-szerű importok no-op-ok. Vagy töröld az `use`-t, vagy ha namespaced fájlban dolgozol, használj fully qualified `\Foo` referenciát.

### `session_start(): Session cannot be started after headers have already been sent`

Egy korábbi PHP warning HTML-be írt és a headers elszálltak. Először a warningot fixáld (ld. fenti use-statement-eset), a session-hiba magától eltűnik.

### `Undefined array key 1` a `Dotenv.php`-ban

A régi `Dotenv` üres/komment sorra elhasalt. A javított verzió már skippeli az üres és `#`-kel kezdődő sorokat, és `putenv()`-vel is exposeolja az értékeket — így a `getenv('APP_SECRET_KEY')` és társai a config fájlokban is működnek.

### `application.secretKey (or APP_SECRET_KEY env) has not been set`

Vagy hozz létre egy `src/Application/application.json`-t `{"application":{"secretKey":"…"}}` tartalommal, vagy (egyszerűbb) tedd be az `APP_SECRET_KEY`-et a `src/.env`-be (`openssl rand -hex 32`).

### `404 No route matched for 'api/v1/auth/register'`

A `ClassExploder::defaultNamespaces` korábban csak az `Application\Controllers`-t scannelte, és a `Framework\Controllers`-t (ahol az `AuthController` van) egy `application.json` flag mögé rejtette. Az új viselkedés: a `Framework\Controllers` **mindig** benne van a discovery-ben. Ha mégis 404-et kapsz, ellenőrizd, hogy az új kontrollered az `Application\Controllers` namespace alá esik-e, és a `#[Path]` attribútum a method-on van-e (nem az osztályon).

### Register dev-link nem jelenik meg a banneren

Két env kell hozzá a `src/.env`-ben:

```
APP_EXPOSE_VERIFICATION_LINK=1
APP_VERIFY_EMAIL_URL=http://localhost:5173/verify-email
```

Ha már regisztráltál a flag-ek nélkül, a token elveszett (a backend nem küldte el, a response sem adta vissza). Töröld a user-t a DB-ből (`DELETE FROM user WHERE email='…'`) vagy aktiváld manuálisan (`UPDATE user SET is_active=1, activation_hash=NULL WHERE id=…`), aztán állítsd be a flag-eket a jövőhöz.

---

## 6. lépés — Production deploy: same-origin SPA build

Production-ben a SPA-t **buildeljük statikussá**, és bemásoljuk a backend webroot-ja alá — az Apache (vagy nginx) ugyanazon origin-en szolgálja a `/` SPA fileokat és az `/api/*` PHP endpointokat. Ennek van három előnye:

- **Egy origin** — a `__Host-refresh` cookie + `SameSite=Strict` minden hop-on érvényesül; nincs CORS preflight overhead.
- **Cache + CDN** — a SPA assetek immutabilis hash-elt fájlnevekkel CDN-eslen szolgálhatók.
- **Egy TLS-cert** — egy domain, egy Let's Encrypt-cert; nincs külön SPA + backend domain.

A repo erre az `APP_SPA_MODE=embedded` mintát szállítja. Az Apache `.htaccess` szabályrendszere fájl-jelenlét alapján vált módot: ha a `src/html/app/index.html` létezik, automatikusan embedded; ha nincs, separate (`/` → 404). A részleteket az [Deployment doksi](deployment.md) tartalmazza — itt egy közvetlen folyamat-recept.

### a) SPA build

```bash
cd examples/react-spa

# Production .env: VITE_API_BASE üres (same-origin), proxyra nincs szükség
# a buildelt static appnek.
printf 'VITE_API_BASE=\n' > .env.production

npm ci
npm run build       # → examples/react-spa/dist/
```

A `dist/` egy klasszikus Vite-output: `index.html` + `assets/<hash>.js` + `assets/<hash>.css`. Az `index.html` history-mode-router-rel megy, tehát a `.htaccess`-nek a nem létező path-okat az `index.html`-re kell fallback-elnie (ezt megteszi, ld. lent).

### b) SPA deploy a webroot alá

```bash
# A src/html/app/ gitignored (.gitkeep kivételével) — biztonságos belerakni a build outputot.
rm -rf src/html/app/*
cp -r examples/react-spa/dist/* src/html/app/
```

Most a fájlszerkezet:

```
src/html/
├── .htaccess           # mod_rewrite szabályok (lent részletezve)
├── index.php           # backend front controller
└── app/
    ├── index.html      # SPA entry — ez aktiválja az embedded módot
    └── assets/
        ├── index-abc123.js
        └── index-def456.css
```

### c) Apache `.htaccess` (már szállítva)

A `src/html/.htaccess` (változatlanul a repo gyökerében) a következő prioritási sorrendet kezeli:

1. `/api/*`, `/healthz`, `/readyz` → `index.php` (backend).
2. `/app/<létező fájl>` → direkt fájl (a Vite asset, hash-elt név).
3. `/app/<nem létező>` → `app/index.html` (SPA history fallback).
4. `/` vagy bármely más path, ha `app/index.html` létezik → `app/index.html`.
5. Egyébként → `index.php` (separate mód fallback).

Tehát a böngészőben `https://app.example.com/profile`-ra menve a `/profile` az SPA-router kezébe kerül, a `https://app.example.com/api/v1/auth/login`-ra menve a PHP-é. **Egy origin, egy cookie-domain, egy CSRF token.**

### d) Backend production `.env`

```bash
# src/.env (prod)
APP_ENV=production              # cookie Secure flag mindig
APP_SPA_MODE=embedded           # ha mégis explicit kell — egyébként a fájl-jelenlét dönt
APP_TRUST_PROXY=1               # TLS-terminating proxy mögött (X-Forwarded-Proto)
APP_FORCE_HTTPS=1               # plain HTTP → 301 HTTPS
APP_RATE_LIMIT=1
APP_RATE_LIMIT_BACKEND=redis    # multi-worker FPM-hez kötelező
REDIS_DSN=tcp://redis:6379
APP_LOG_LEVEL=INFO
APP_DI_COMPILE=1                # php-di compiled container

# A dev-only flag-ek HAGYD ÜRESEN / 0-n
APP_EXPOSE_VERIFICATION_LINK=0

# SMTP a verify-emailhez (egyébként a user nem aktivál)
# A `Application/application.json` `smtp` szekciójának is élnie kell.

APP_SECRET_KEY=<openssl rand -hex 32 — egyszer, soha ne forgasd>
APP_VERIFY_EMAIL_URL=https://app.example.com/verify-email
```

### e) Composer + cache warm

```bash
cd src
composer install --no-dev --optimize-autoloader

# Route cache warm — production-ben nem akarunk reflection-scant minden requesten.
vendor/bin/doctrine-migrations migrations:migrate --no-interaction
php bin/console route:cache         # → var/cache/routes.php
php bin/console openapi:dump        # → var/cache/openapi.json
```

### f) JWT kulcs-pár

Production-ben **NE a dev `var/keys/`-kulcsokat** használd (gitignore-olva, de első buildkor csak dev-célra generálódnak). Két opció:

**Fájl-alapú (klasszikus):**

```bash
mkdir -p src/var/keys
cd src/var/keys
openssl genrsa -out jwt-private.pem 4096
openssl rsa -in jwt-private.pem -pubout -out jwt-public.pem
chmod 600 jwt-private.pem
```

A `config/jwt.php` defaultban a `var/keys/jwt-{private,public}.pem` útra esik vissza. Konténerben mountold be ezeket Docker secret-ként vagy k8s Secret-ből.

**Env-alapú (k8s/CI-barátabb):**

```bash
# A teljes PEM-tartalmat env változón át add be — a config/jwt.php előbb ezt nézi.
export JWT_PRIVATE_KEY="$(cat jwt-private.pem)"
export JWT_PUBLIC_KEY="$(cat jwt-public.pem)"
```

Vagy override-old a fájlútvonalakat is: `JWT_PRIVATE_KEY_PATH=/run/secrets/jwt-private.pem`.

### g) Smoke test

```bash
curl -fsS https://app.example.com/api/v1/healthz
# {"status":"ok","time":"…"}

curl -fsS https://app.example.com/api/v1/readyz
# {"status":"ready","checks":{"database":"ok"}}

# A SPA elérhető
curl -fsSI https://app.example.com/ | head -1
# HTTP/2 200
```

A teljes prod stack-et a [docker-compose.prod.yml](https://github.com/csekme/antarctic/blob/main/docker-compose.prod.yml) szállítja: multi-stage Dockerfile, PHP-FPM + opcache + JIT, nginx + FastCGI, Redis a rate-limit-hez. Részletek a [Deployment / Production stack](deployment.md#production-stack-docker-composeprodyml) szekcióban.

### Mire figyelj még

| Téma | Hova nézz |
|------|-----------|
| CORS letiltása embedded módban | [HTTP / CORS](http/cors.md) — separate módnál kell csak |
| Cookie Secure flag a TLS-proxy mögött | [Auth / JWT](auth/jwt.md) — `APP_TRUST_PROXY=1` |
| OpenAPI doksi production-szolgáltatása | a `/api/v1/docs.json` route-on át — gating-eld auth mögé ha érzékeny |
| Migrációk a release-pipeline-ban | `doctrine-migrations migrate --no-interaction` a deploy script-ben, blue-green deploy esetén külön round |
| Log-aggregátor (Datadog, Loki, …) | a Monolog stdout-ra JSON-t ad, tehát a docker logs vagy a sidecar ingestible |

---

## Következő lépések

- [Architektúra](architecture.md) — a kérés-feldolgozás teljes útja
- [HTTP / Routing](http/routing.md) — `#[Path]`, URL paraméterek, route cache
- [HTTP / Middleware](http/middleware.md) — saját PSR-15 middleware írása
- [Auth / JWT](auth/jwt.md) — access + refresh token rotáció, RS256 kulcsok
- [Auth / Endpoints](auth/endpoints.md) — register, verify-email, login, 2FA enroll/confirm/disable
- [Adatbázis](database.md) — Dal, repository pattern, Doctrine Migrations
- [Konfiguráció](configuration.md) — env változók, JSON config, security headers
- [Tesztelés](testing.md) — PHPUnit setup, sqlite memory fixture
- [Deployment](deployment.md) — production hardening, composer dump-autoload, route cache warm
