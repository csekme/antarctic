# CORS

A `CorsMiddleware` engedélyezi a cross-origin SPA klienseket. Allow-list alapú, és **separate deploy** módban kötelező; **drop-in / embedded** módban (a SPA azonos origin alól szolgálva) általában nincs rá szükség.

## Konfiguráció

A konfig fájl: [`src/config/cors.php`](https://github.com/csekme/antarctic/blob/main/src/config/cors.php).

```php
return [
    'allowed_origins' => [],                              // ⚠️ env-ből töltődik
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Authorization', 'Content-Type', 'X-Csrf-Token', 'X-Requested-With'],
    'exposed_headers' => ['X-Request-Id'],
    'allow_credentials' => true,
    'max_age' => 600,
];
```

### Origins env-változóból

Az `allowed_origins` listát a `CORS_ALLOWED_ORIGINS` környezeti változó tölti, vesszővel elválasztva:

```bash
# .env
CORS_ALLOWED_ORIGINS=https://app.example.com,https://staging.example.com
```

Üres lista (nincs env változó) → minden cross-origin kérést elutasít. Erre van szükség drop-in deploy-nál.

### Wildcard

`['*']` érték minden origint megenged. Csak akkor használd, ha az `allow_credentials` `false` — különben a böngészők elutasítják a választ (`Access-Control-Allow-Origin: *` és `Access-Control-Allow-Credentials: true` egyszerre nem érvényes).

## Mit csinál a middleware

| Helyzet | Eredmény |
|---|---|
| Nincs `Origin` header | Pass-through, semmilyen CORS header nem kerül a válaszra (same-origin kérés). |
| `Origin` engedélyezett + nem-OPTIONS | A handler lefut, a választ a middleware megdíszíti `Access-Control-Allow-Origin`, `Vary: Origin`, és (ha kell) `Access-Control-Allow-Credentials` + `Access-Control-Expose-Headers` headerekkel. |
| `Origin` engedélyezett + OPTIONS preflight | **Short-circuit**: a handler nem fut le. 204-es válasz `Access-Control-Allow-Methods`, `…Allow-Headers`, `…Max-Age` headerekkel. |
| `Origin` nincs az allow-list-en + OPTIONS | 403 Forbidden. |
| `Origin` nincs az allow-list-en + egyéb method | A handler lefut, **de a válasz nem kap CORS headert** → a böngésző elutasítja. |

## Példák

### Drop-in deploy (egy origin, nincs CORS)

```bash
# .env — nincs CORS_ALLOWED_ORIGINS
```

A `cors.php` üres `allowed_origins`-szal tér vissza. A SPA azonos origin alól megy, a CORS middleware csendben pass-through-ol.

### Separate deploy (két origin)

```bash
# Backend .env
CORS_ALLOWED_ORIGINS=https://app.example.com
APP_SPA_MODE=separate
```

```javascript
// SPA fetch
fetch('https://api.example.com/api/v1/me', {
  credentials: 'include',  // refresh cookie elküldése
  headers: { 'Authorization': `Bearer ${accessToken}` },
});
```

### Vite dev-proxy (helyi fejlesztés)

A javasolt minta: a Vite dev-szerver proxyzza a `/api`-t a backendhez, így a böngésző szempontjából **azonos origin**.

```ts
// vite.config.ts
export default defineConfig({
  server: {
    proxy: {
      '/api': { target: 'http://localhost:8080', changeOrigin: true },
    },
  },
});
```

Ekkor nincs szükség `CORS_ALLOWED_ORIGINS`-ra.

## Tesztelés

A middleware bármilyen handler ellen tesztelhető:

```php
$middleware = new CorsMiddleware([
    'allowed_origins' => ['https://app.example.com'],
    'allow_credentials' => true,
]);

$request = (new ServerRequest('OPTIONS', '/api/v1/x'))
    ->withHeader('Origin', 'https://app.example.com')
    ->withHeader('Access-Control-Request-Method', 'POST');

$response = $middleware->process($request, $someHandler);
$this->assertSame(204, $response->getStatusCode());
```

Részletes teszteset: [`tests/Framework/Http/CorsMiddlewareTest.php`](https://github.com/csekme/antarctic/blob/main/src/tests/Framework/Http/CorsMiddlewareTest.php).

## Biztonsági megjegyzések

- **`allow_credentials: true` + wildcard origin tilos** — sebezhetőség. A middleware nem ellenőrzi ezt automatikusan, te magad ügyelj rá.
- A `Vary: Origin` header automatikusan kerül minden CORS-os válaszra, hogy a cache rétegek (Varnish, CDN) ne keverjék össze a különböző originek válaszait.
- Pre-flight `Access-Control-Max-Age` defaultja **600 másodperc** — a böngésző 10 percig nem küld újabb preflight-ot ugyanazon endpoint+method+headers kombinációra. Túl magas érték hibás CORS konfig miatt nehezen javítható.
