# Request és Response

Az Antarctic **két szinten** dolgozik a HTTP üzenetekkel:

- **PSR-7** a pipeline szintjén (middleware-ek között) — `Psr\Http\Message\ServerRequestInterface` és `ResponseInterface`.
- **Framework Request/Response** a kontroller szintjén — egyszerűbb public-property API, ami a tesztelhető DTO + autowire mintára optimalizált.

A `Framework\Http\HttpAdapter` váltja át a kettőt a `LegacyDispatcherMiddleware` határán. Új middleware-t PSR-7-tel írj; új kontrollert a Framework Request/Response párossal — a `Dispatcher` mindkettőt konstruktor-injektálja, és DTO típushintet is automatikusan hidrál.

## A Framework Request

Az osztály a `src/Framework/Request.php`-ben. Public property-jei:

```php
public string $uri;        // = $_SERVER['QUERY_STRING'] a .htaccess rewrite után
public string $method;     // GET, POST, …
public array  $get;        // $_GET
public array  $post;       // $_POST
public array  $files;      // $_FILES
public array  $cookie;     // $_COOKIE
public array  $server;     // $_SERVER
public array  $json;       // JSON body parse-olva (ha Content-Type: application/json)
```

Hasznos metódusok:

```php
$request->getJson();             // a JSON body asszociatív tömbként
$request->isContentTypeJson();   // bool
$request->getCSRFFromHeader();   // ?string — X-CSRF-TOKEN
```

### Példa kontrollerből

```php
class LoginController extends AbstractController
{
    #[Path(path: '/api/v1/auth/login', method: 'POST')]
    public function login(): Response
    {
        $body = $this->request->getJson();
        $email = $body['email'] ?? null;
        // ...
    }
}
```

A `$this->request` az `AbstractController` konstruktorán keresztül érkezik — a Dispatcher a php-di `make()` named-param override-jával adja át a per-request `Request`-et. Saját kontroller esetén kérheted egyszerűen a `parent::__construct($request, $response, $route_params)`-szal, vagy a leszármazottban átveheted bármilyen további konstruktor-paraméterrel (autowire).

## A Framework Response

A `src/Framework/Response.php`-ben. Belül egyszerű — body string, header tömb, status code.

### Konstruktor / static factory

```php
$response = new Response();                            // üres, status 0 (= 200 az adapterben)
$response = Response::json(['ok' => true], 201);       // JSON factory
```

### Setterek

```php
$response->setBody('Hello world');
$response->setStatusCode(204);
$response->addHeader('X-Custom: value');
$response->redirect('/login');                          // Location header
```

### Getterek

```php
$response->getBody();           // string
$response->getStatusCode();     // int
$response->getHeaders();        // string[] — raw "Header-Name: value" sorok
```

A `HttpAdapter::toPsrResponse()` ezeket használja a PSR-7 átalakításhoz; szabadon hívhatod a saját kódodban is.

## PSR-7 használata közvetlenül

Ha egy saját middleware-t írsz, **PSR-7 üzenetekkel** dolgozol. A `Nyholm\Psr7\Response` immutable, "with-style" API:

```php
$response = new \Nyholm\Psr7\Response(200, ['Content-Type' => 'application/json']);
$response = $response->withHeader('X-Custom', 'value');
$response->getBody()->write('{"ok":true}');
return $response;
```

A request immutability fontos: minden `with*` metódus **új** objektumot ad. A régi `$request` változatlan marad.

```php
$newRequest = $request->withAttribute('user', $user);
return $handler->handle($newRequest);   // a downstream az újat látja
```

## HttpAdapter: PSR-7 ↔ legacy

A `Framework\Http\HttpAdapter` két static metódussal váltja a két formát:

```php
// PSR-7 ServerRequest → legacy Framework\Request
$legacy = HttpAdapter::toLegacyRequest($psrRequest);

// legacy Framework\Response → PSR-7 Response
$psr = HttpAdapter::toPsrResponse($legacyResponse);
```

Az adapter megőrzi a legacy szemantikát (pl. `$request->uri` = `$_SERVER['QUERY_STRING']`), tehát a régi kontrollerek transzparensen működnek a PSR-15 pipeline alatt.

A teljes adapter logika és viselkedési tesztek: [`tests/Framework/Http/HttpAdapterTest.php`](https://github.com/csekme/antarctic/blob/main/src/tests/Framework/Http/HttpAdapterTest.php).

## Mikor melyiket használd

| Helyzet | Használd |
|---|---|
| Új middleware írása | PSR-7 (`ServerRequestInterface`, `ResponseInterface`) |
| Kontroller method írása | `Framework\Request` / `Response` (a Dispatcher ezt injektálja) |
| JSON válasz | `Response::json([...])` |
| Validált JSON body | DTO típushint a paraméterben (`CreateUserRequest $dto`) — automatikus hydrate + validate |
| Body hozzáférés middleware-ben | `$request->getParsedBody()` (form) vagy `(string) $request->getBody()` (raw) |
| Header hozzáférés middleware-ben | `$request->getHeaderLine('X-Header')` |
