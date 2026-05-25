# Konfiguráció

Az Antarctic háromféle forrásból olvas konfigurációt:

1. **`.env`** — secrets és környezet-specifikus változók (DB jelszó, JWT kulcs path, CORS origin lista). Soha ne commitold.
2. **`src/Application/application.json`** — alkalmazás-szintű beállítások (app név, admin email, SMTP, framework flags). Gitignored.
3. **`src/config/*.php`** — PHP konfigurációs fájlok komponensekhez (`cors.php`, később `jwt.php`, `security.php`).

## `.env`

A `Framework\Dotenv` betölti a `ROOT_PATH . '/.env'` fájlt. Egyszerű `KEY=value` szintaxis, idézőjelek opcionálisak.

```bash
# src/.env
APP_ENV=local
APP_DEBUG=true

DATABASE_HOST=database
DATABASE_PORT=5432
DATABASE_NAME=antarctic
DATABASE_USER=antarctic
DATABASE_PASSWORD=secret

# CORS — vesszővel elválasztott origins
CORS_ALLOWED_ORIGINS=http://localhost:5173,https://app.example.com

# SPA mód — embedded | separate | both
APP_SPA_MODE=embedded

# JWT — kulcs útvonalak (M2.a óta)
JWT_PRIVATE_KEY_PATH=var/keys/jwt-private.pem
JWT_PUBLIC_KEY_PATH=var/keys/jwt-public.pem

# --- M5: production hardening ---

# Strukturált JSON log szint és csatorna
APP_LOG_CHANNEL=app
APP_LOG_LEVEL=INFO

# TLS-terminating reverse proxy mögött kapcsold be — X-Forwarded-Proto és
# X-Forwarded-For honorálása (SecurityHeaders, HttpsRedirect, RateLimit).
APP_TRUST_PROXY=1

# Plain HTTP → HTTPS 301 redirect; healthz/readyz kivétel
APP_FORCE_HTTPS=1

# Security headers env-overridek (default-ok jók a legtöbb esetben)
APP_CSP=default-src 'self'; frame-ancestors 'none'
APP_HSTS_MAX_AGE=31536000

# Rate limit — Redis store production-höz
APP_RATE_LIMIT=1
APP_RATE_LIMIT_BACKEND=redis
REDIS_DSN=tcp://redis:6379
REDIS_KEY_PREFIX=rl:

# php-di compile cache (production)
APP_DI_COMPILE=1
```

Részletes M5 env változók: [Observability](observability.md), [Security headers](http/security-headers.md), [Rate limit](http/rate-limit.md), [Deployment](deployment.md#production-stack-docker-compose-prod-yml).

Hozzáférés: `getenv('DATABASE_HOST')` vagy `$_ENV['DATABASE_HOST']`.

!!! warning "Migrációs irány (M0 ↔ M2)"
    A `Framework\Dotenv` saját implementáció. A `vlucas/phpdotenv` átállás (kötelező változók validációja, type cast, immutable env) az M2 keretein belül történik. Az alapvető `getenv()` interfész nem változik.

## `application.json`

Az alkalmazás JSON konfigja a `src/Application/application.json` (gitignored — saját example fájlból másold).

Példa struktúra:

```json
{
  "administrator": {
    "email": "admin@example.com"
  },
  "application": {
    "name": "My Antarctic App",
    "description": "Demo application",
    "secretKey": "change-me",
    "interceptors": [
      {
        "name": "Interceptors\\LogInterceptor",
        "call-chain": "before",
        "enabled": true
      }
    ]
  },
  "framework": {
    "cache": false,
    "showErrors": true,
    "useCoreControllers": true
  },
  "smtp": {
    "host": "smtp.example.com",
    "port": 587,
    "username": "noreply@example.com",
    "password": "..."
  },
  "server": {
    "protocol": "https"
  }
}
```

Hozzáférés a `Framework\Config` osztályon keresztül:

```php
use Framework\Config;

Config::get_config();                     // teljes asszociatív tömb
Config::get_server_protocol();            // 'http' default
Config::useCache();                       // bool
Config::useCoreController();              // bool — Framework\Controllers scan
Config::show_errors();                    // bool — debug mód
Config::get_interceptors();               // array
```

### Fontos flagek

| Kulcs | Hatás |
|---|---|
| `framework.showErrors` | `true` → részletes hiba payload (lásd [hibakezelés](http/error-handling.md)). Production-ben `false`. |
| `framework.useCoreControllers` | `true` → a `Framework\Controllers\*` (Login, Signup, 2FA) is route-olódik. **M2.d-ben kivezetve**, mindenképp `false` lesz a default. |
| `framework.cache` | `true` → DB cache (rétegrendszer szerint). |
| `application.secretKey` | Régi session-tokenhez használt. **M2-ben kivezetve**, JWT kulcsra cserélve. |

## `src/config/*.php`

PHP-fájl alapú konfiguráció minden olyan komponensnek, ami struktúrált tömböt vár. A fájl `return [...]`-tal végződik.

### Komponens-config fájlok

| Fájl | Tartalom | Doksi |
|---|---|---|
| `src/config/cors.php` | Allow-list origins, methods, headers, credentials | [CORS](http/cors.md) |
| `src/config/jwt.php` | Algoritmus, kulcs paths, TTL-ek (access 15min, refresh 30 nap) | [JWT és TokenService](auth/jwt.md) |
| `src/config/rate-limit.php` | Path-prefix bucket-ek + backend selector (memory \| redis) | [Rate limit](http/rate-limit.md) |
| `src/config/security-headers.php` | HSTS, CSP, X-Frame-Options, Referrer-Policy értékek | [Security headers](http/security-headers.md) |

## Konvenciók

1. **Secret → `.env`**. Soha ne tedd JSON-be vagy PHP fájlba ami később commitolódik.
2. **App-szintű kapcsoló → `application.json`**. Bool flag-ek, e-mail címek, SMTP adatok.
3. **Komponens-szintű struktúra → `config/*.php`**. CORS allow-list, validátor szabályok, route-cache opciók.
4. **Env-override**. Ahol a komponens config függhet env-től (mint a CORS origins), a PHP fájl `getenv()` -tel olvas, fallback default-tal.

## Példa: új komponens-config bekötése

Tegyük fel, hogy egy saját `Application\Http\Middleware\AuditLogMiddleware`-t építesz, és külön config fájlt szeretnél hozzá.

1. Hozd létre `src/config/audit-log.php`:

   ```php
   return [
       'enabled' => filter_var(getenv('APP_AUDIT_LOG') ?: '0', FILTER_VALIDATE_BOOL),
       'channels' => [
           '/api/v1/auth/' => 'security',
           '/api/v1/admin/' => 'admin',
       ],
   ];
   ```

2. Töltsd be a Bootstrap-ben — döntsd el a beépített middleware-sorrenden belül, hová való:

   ```php
   $auditConfig = require ROOT_PATH . '/config/audit-log.php';
   if ($auditConfig['enabled']) {
       // A `CorsMiddleware` után, hogy a preflight-ok ne kerüljenek auditba.
       $middlewares[] = new \Application\Http\Middleware\AuditLogMiddleware(
           channels: $auditConfig['channels'],
           logger: $logger,
       );
   }
   ```

3. A middleware konstruktora típushintelten várja a struktúrát:

   ```php
   public function __construct(
       /** @var array<string, string> $channels */
       private readonly array $channels,
       private readonly LoggerInterface $logger,
   ) {}
   ```

Az env-flag mögötti opt-in mintát a M4.b.4 (rate-limit) és M5.c (HTTPS redirect) is követi — production-ben kapcsold be, dev-ben hagyd kikapcsolva.
