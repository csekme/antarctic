# Rate limit

PSR-15 middleware, ami konfigurálható path-prefix-bucket-ek alapján throttle-olja a kéréseket. Master kapcsoló a `config/rate-limit.php` `enabled` flag-jén (env `APP_RATE_LIMIT`), így dev és tesztek default-throttling-mentesen futnak.

## Mit véd alapból

| Path prefix | Limit | Ablak | Kulcs |
|---|---|---|---|
| `/api/v1/auth/login` | 5 | 60 mp | IP |
| `/api/v1/auth/2fa/verify` | 5 | 60 mp | IP |
| `/api/v1/` (catch-all) | 120 | 60 mp | IP |

Az első illeszkedő szabály nyer — narrower elöl, catch-all hátul.

## Példa 429 válasz

```http
HTTP/1.1 429 Too Many Requests
Content-Type: application/problem+json; charset=utf-8
Retry-After: 47
X-RateLimit-Limit: 5
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1737641600

{
  "type": "about:blank",
  "title": "Too Many Requests",
  "status": 429,
  "detail": "Rate limit exceeded. Retry in 47 seconds.",
  "instance": "/api/v1/auth/login"
}
```

A sikeres kérések is megkapják az `X-RateLimit-Limit / -Remaining / -Reset` informatív headereket, ha a rule match-elt.

## Konfiguráció

`config/rate-limit.php`:

```php
return [
    'enabled' => filter_var(getenv('APP_RATE_LIMIT') ?: '0', FILTER_VALIDATE_BOOL),
    'trust_proxy' => filter_var(getenv('APP_TRUST_PROXY') ?: '0', FILTER_VALIDATE_BOOL),
    'rules' => [
        ['name' => 'auth-login',  'path_prefix' => '/api/v1/auth/login',      'limit' => 5,   'window' => 60],
        ['name' => 'auth-2fa',    'path_prefix' => '/api/v1/auth/2fa/verify', 'limit' => 5,   'window' => 60],
        ['name' => 'api-default', 'path_prefix' => '/api/v1/',                'limit' => 120, 'window' => 60],
    ],
];
```

| Kulcs | Jelentés |
|---|---|
| `enabled` | Master kapcsoló — ha `false`, a middleware sosem regisztrálódik (zero-overhead). |
| `trust_proxy` | Ha `true`, az `X-Forwarded-For` első tagját veszi IP-nek. **Csak megbízható reverse-proxy mögött kapcsold be!** |
| `rules[*].path_prefix` | `str_starts_with` match. |
| `rules[*].limit` | Maximum kérés / ablak. |
| `rules[*].window` | Ablak hossza másodpercben. |
| `rules[*].key` | `"ip"` (default) vagy `"user"` — az utóbbi az authentikált user-id-t veszi bucket-kulcsnak; anonim → IP fallback. |
| `rules[*].name` | Opcionális, debug-célokra a bucket-kulcs prefix-jébe kerül. |

## Bootstrap kötés

A `Framework\Bootstrap` `CorsMiddleware` után, `AuthMiddleware` előtt csatolja:

```php
$rateLimitConfig = require ROOT_PATH . '/config/rate-limit.php';
if (RateLimitConfig::isEnabled($rateLimitConfig)) {
    $middlewares[] = new RateLimitMiddleware(
        rules: RateLimitConfig::rulesFromArray($rateLimitConfig),
        store: new InMemoryStore(),
        clock: new SystemClock(),
        trustProxy: RateLimitConfig::trustProxy($rateLimitConfig),
    );
}
```

A pipeline-pozíció (CORS után) azért fontos, hogy a preflight `OPTIONS` ne számítson bele a bucketbe.

## Production: Redis backend

Az `InMemoryStore` per-process — multi-worker FPM-ben minden worker külön bucketet vezet, így egy IP minden worker-nyi-szer érheti el a limit-et. Az M5 óta szállítjuk a `RedisStore`-t és a `PredisAdapter`-t (`predis/predis` alapú); az M5 follow-upban érkezett a `PhpRedisAdapter` is (`ext-redis` natív kliens). Production deployhoz a config `backend` kulcsát állítsd be:

```bash
APP_RATE_LIMIT_BACKEND=redis        # vagy "predis" alias, vagy "phpredis"
REDIS_DSN=tcp://redis:6379
REDIS_KEY_PREFIX=rl:
```

A `Bootstrap.php` `match`-eli a backend nevet:

| `APP_RATE_LIMIT_BACKEND` | Store | Adapter | Függőség |
|---|---|---|---|
| `memory` (default) | `InMemoryStore` | — | — |
| `redis` / `predis` | `RedisStore` | `PredisAdapter` | `predis/predis` (composer) |
| `phpredis` | `RedisStore` | `PhpRedisAdapter` | `ext-redis` PECL |

Az `INCR + EXPIRE` atomicity-t mindkét Redis-adapter egy Lua scripttel (`EVAL`) garantálja: az első `INCR`-kor (count==1) áll be a TTL, későbbi hitekkor nem nyúl hozzá. Naïv `INCR; EXPIRE` két parancsként race-elne — két konkurens process közé eshet egy `INCR` egy `EXPIRE` nélkül, és a bucket örökre megmaradna.

### Saját adapter (RoadRunner KV, Memcached, ...)

A `RedisLike` interface minimális: két metódus (`incrementAndExpire`, `ttl`). Bármilyen KV-szerű backend mögé írhatsz adapter osztályt:

```php
final class CustomAdapter implements RedisLike
{
    public function __construct(private readonly object $client) {}

    public function incrementAndExpire(string $key, int $ttl): int { /* ... */ }
    public function ttl(string $key): int { /* ... */ }
}
```

## User-key stratégia

Az authentikált endpointokon érdemes a user-id-re kötni a bucket-et:

```php
['path_prefix' => '/api/v1/heavy-op', 'limit' => 10, 'window' => 60, 'key' => 'user']
```

Anonim hozzáférés esetén automatikusan IP-fallback.

## Tesztelés

A middleware fagyasztott órával teszthető:

```php
$middleware = new RateLimitMiddleware(
    rules: [new RateLimitRule('/api/v1/auth/login', 5, 60)],
    store: new InMemoryStore(),
    clock: new FrozenClock(1_000),
);

for ($i = 0; $i < 5; $i++) {
    $middleware->process($req, $ok);
}
$blocked = $middleware->process($req, $ok);
$this->assertSame(429, $blocked->getStatusCode());
```

A `Psr\Clock\ClockInterface`-t a teszt-suite kis inline implementációkkal pótolja; lásd [`RateLimitMiddlewareTest`](../../../src/tests/Framework/Http/RateLimit/RateLimitMiddlewareTest.php).

## Mit nem kezel

- **DDoS** — a middleware per-IP throttle, nem helyettesíti a CDN/WAF szintű védelmet.
- **Globális process-throughput** — minden bucket per-key; ha az egész alkalmazás védelme kell, külön catch-all rule a `/`-re.
- **Adaptív thresholding** — fix `limit/window`; sliding window vagy token bucket variánsok későbbi optimalizáció.
- **CAPTCHA / fail2ban integráció** — most csak throttle; további lépés (pl. CAPTCHA-trigger N kísérlet után) más middleware-be tartozna.
