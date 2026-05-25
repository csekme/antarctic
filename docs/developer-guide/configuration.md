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

# SPA mód — embedded | separate | both (M3-ban épül)
APP_SPA_MODE=embedded

# JWT — M2-ben épül
JWT_PRIVATE_KEY_PATH=var/keys/jwt-private.pem
JWT_PUBLIC_KEY_PATH=var/keys/jwt-public.pem
```

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

### `cors.php`

Lásd: [HTTP / CORS](http/cors.md).

### Jövőbeli fájlok

| Fájl | Tartalom | PR |
|---|---|---|
| `src/config/jwt.php` | Algoritmus, kulcs paths, TTL-ek (access 15min, refresh 30 nap) | M2.a |
| `src/config/security.php` | HSTS, CSP, X-Frame-Options értékek | M5 |
| `src/config/rate-limit.php` | Endpoint-onkénti limitek | M4.b |

## Konvenciók

1. **Secret → `.env`**. Soha ne tedd JSON-be vagy PHP fájlba ami később commitolódik.
2. **App-szintű kapcsoló → `application.json`**. Bool flag-ek, e-mail címek, SMTP adatok.
3. **Komponens-szintű struktúra → `config/*.php`**. CORS allow-list, validátor szabályok, route-cache opciók.
4. **Env-override**. Ahol a komponens config függhet env-től (mint a CORS origins), a PHP fájl `getenv()` -tel olvas, fallback default-tal.

## Példa: új komponens-config bekötése

Tegyük fel, hogy `Application\Http\Middleware\RateLimitMiddleware`-t építesz, és külön config fájlt szeretnél hozzá.

1. Hozd létre `src/config/rate-limit.php`:

   ```php
   return [
       'default' => ['limit' => 60, 'window' => 60],
       'auth_login' => ['limit' => 5, 'window' => 60],
   ];
   ```

2. Töltsd be a Bootstrap-ben:

   ```php
   $rateLimitConfig = require ROOT_PATH . '/config/rate-limit.php';
   $pipeline = new MiddlewarePipeline([
       new ErrorHandlerMiddleware(debug: $debug),
       new CorsMiddleware($corsConfig),
       new \Application\Http\Middleware\RateLimitMiddleware($rateLimitConfig),
       new LegacyDispatcherMiddleware($dispatcher),
   ], new NotFoundHandler());
   ```

3. A middleware konstruktora típushintelten várja a tömböt:

   ```php
   public function __construct(
       /** @var array<string, array{limit: int, window: int}> */
       private readonly array $config,
   ) {}
   ```
