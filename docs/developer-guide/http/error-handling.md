# Hibakezelés (RFC 7807)

Az `ErrorHandlerMiddleware` az egész pipeline külső rétege: bármi Throwable, ami a downstream-ben elszáll, itt akad fenn és válik egy strukturált HTTP válasszá.

A formátum a kliens szándékától függ:

- **JSON-kliens** (`Accept: application/json` vagy `/api/*` path) → [RFC 7807 problem+json](https://datatracker.ietf.org/doc/html/rfc7807).
- **Böngésző** (`Accept: text/html` vagy semmi explicit) → minimal HTML hibaoldal.

## Status code mapping

A middleware az exception `getCode()` értékét veszi figyelembe:

| Exception code | Válasz status |
|---|---|
| 400–599 (érvényes HTTP error) | Változatlan |
| Bármi más | 500 |

Tehát ha tudni szeretnéd, hogy egy 404-et fog adni a kliensnek, dobd így:

```php
throw new \RuntimeException('User not found', 404);
```

## Problem+json envelope

A `Content-Type: application/problem+json; charset=utf-8` válasz a következő struktúrát adja:

```json
{
  "type": "about:blank",
  "title": "Not Found",
  "status": 404,
  "detail": "User not found",
  "instance": "/api/v1/users/9999"
}
```

A mezők [RFC 7807](https://datatracker.ietf.org/doc/html/rfc7807) szerint:

- **`type`** — URI a hiba típusához (`about:blank` az általános default).
- **`title`** — rövid, human-readable összefoglaló.
- **`status`** — HTTP status code másolva.
- **`detail`** — részletes szöveg (a `Throwable::getMessage()` 4xx esetén).
- **`instance`** — az URL path, ahol a hiba történt.
- **`errors`** (opcionális) — `Framework\Validation\ValidationException` esetén property-path → list<string> map (`{"email": ["Email is required."]}`). Részletek: [Validáció (Request DTO-k)](validation.md).

### Debug mód

`Bootstrap.php`-ban a `Config::show_errors()` adja a debug flaget. Ha `true`:

- 5xx hibák is megkapják az eredeti `Throwable::getMessage()`-et a `detail`-ben.
- A response payload tartalmaz `exception`, `file`, `line` mezőket is.
- HTML válasz tartalmazza a teljes stack trace-t `<pre>`-ben.

Production-ben **legyen kikapcsolva** (`framework.showErrors: false` az `application.json`-ben), különben SQL hibaüzenetek, credential maradványok, fájl-elérési utak szivároghatnak.

## Leak-guard

Debug **kikapcsolva** esetén az 5xx válaszok `detail`-je mindig statikus:

```json
{
  "type": "about:blank",
  "title": "Internal Server Error",
  "status": 500,
  "detail": "Internal server error.",
  "instance": "/api/v1/something"
}
```

Az eredeti exception üzenete a logba megy (PSR-3 logger), nem a kliens felé.

## Content negotiation szabályok

A `ContentNegotiation::wantsJson()` döntésrendje:

1. Ha a path `/api/`-val kezdődik → JSON.
2. Ha nincs `Accept` header → HTML.
3. Egyébként q-érték összevetés: a JSON-rangot vagy HTML-rangot magasabbra értékelte a kliens?

Példák:

| Path | `Accept` | Eredmény |
|---|---|---|
| `/api/v1/x` | bármi | JSON |
| `/dashboard` | `application/json` | JSON |
| `/dashboard` | `text/html,application/json;q=0.5` | HTML |
| `/dashboard` | `application/problem+json` | JSON |
| `/dashboard` | (nincs) | HTML |

## HTTP method status mapping referencia

A middleware csak az alapokra (`reasonPhrase()`) ismer címkét. Ha sajátot adsz vissza, írhatsz egyedi `title`-t a saját kontroller-szinten:

| Status | Title |
|---|---|
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 405 | Method Not Allowed |
| 409 | Conflict |
| 415 | Unsupported Media Type |
| 422 | Unprocessable Entity |
| 429 | Too Many Requests |
| 500 | Internal Server Error |
| 502 | Bad Gateway |
| 503 | Service Unavailable |
| egyéb | Error |

## Hibák a saját kontrollerben

Egyszerűen dobj exception-t a megfelelő status code-dal:

```php
class UserController extends AbstractController
{
    #[Path(path: '/api/v1/users/{id:\d+}', method: 'GET')]
    public function show(int $id): Response
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            throw new \DomainException("User {$id} not found", 404);
        }
        return Response::json($user->toArray());
    }
}
```

A middleware ezt automatikusan formázza a kliens szándékának megfelelően.

!!! tip "Egyedi exception típusok"
    Érdemes saját exception hierarchiát építeni az `Application\Exception\` alá (`NotFoundException`, `ValidationException`, `AuthException`), hogy a kódkonzisztens legyen, és a middleware-en kívül is típusosan kezelhesd őket.

## Logging

A middleware-be opcionálisan beadhatsz egy `Psr\Log\LoggerInterface`-t:

```php
new ErrorHandlerMiddleware(debug: false, logger: $monolog);
```

Az 5xx hibák ekkor `error` szinten logolódnak `exception`, `file`, `line`, `trace` kontextussal. A 4xx-ek nem — azok várt válaszok (rossz kliens-input, nem szerverhiba).

!!! info "Strukturált JSON log (M5)"
    Az M5 PR-ben a Monolog `JsonFormatter`-rel stdout-ra logolunk minden requestet trace-ID-val (`X-Request-Id`), így a hibákat össze lehet majd kötni a kérés-folyamattal.

## Tesztelés

```php
$middleware = new ErrorHandlerMiddleware(debug: false);
$handler = $this->handlerThrowing(new \Exception('not found', 404));

$request = new ServerRequest('GET', '/api/v1/users/1');
$response = $middleware->process($request, $handler);

$this->assertSame(404, $response->getStatusCode());
$body = json_decode((string) $response->getBody(), true);
$this->assertSame('Not Found', $body['title']);
```

Teljes példatár: [`tests/Framework/Http/ErrorHandlerMiddlewareTest.php`](https://github.com/csekme/antarctic/blob/main/src/tests/Framework/Http/ErrorHandlerMiddlewareTest.php).
