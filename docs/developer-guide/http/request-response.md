# Request és Response

Az Antarctic jelenleg **két szinten** dolgozik a HTTP üzenetekkel:

- **PSR-7** a pipeline szintjén (middleware-ek között) — `Psr\Http\Message\ServerRequestInterface` és `ResponseInterface`.
- **Legacy** `Framework\Request` és `Framework\Response` a kontroller szintjén — egyszerűbb public-property API.

A `Framework\Http\HttpAdapter` váltja át a kettőt a `LegacyDispatcherMiddleware` határán.

!!! note "M3 utáni célállapot"
    Az M3.b–c PR-ek után a kontrollerek közvetlenül PSR-7 üzenetekkel dolgoznak, és a legacy API kivezetésre kerül. A jelenlegi adapter réteg addig garantálja a kompatibilitást.

## Legacy Request

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

A `$this->request` az `AbstractController::setRequest()`-en keresztül kerül beinjektálásra a Dispatcher által.

## Legacy Response

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

### Getterek (M1.a-ban hozzáadva)

```php
$response->getBody();           // string
$response->getStatusCode();     // int
$response->getHeaders();        // string[] — raw "Header-Name: value" sorok
```

A getterek kellettek a PSR-7 adapternek; nyugodtan használhatod a saját kódodban is.

## A View helper

A `Framework\View` Twig template-eket renderel:

```php
class HomeController extends AbstractController
{
    #[Path(path: '/', method: 'GET')]
    public function index(): Response
    {
        return $this->view('home.twig', ['name' => 'Antarctic']);
    }
}
```

A `view()` metódus belül a `View::renderTemplate()`-et hívja, a generált HTML-t a `$this->response`-ba teszi és visszaadja.

!!! warning "Twig kivezetés (M2.d)"
    A Twig view réteg az M2.d PR-ben kivezetésre kerül a session-alapú UI-val együtt. Új kódot inkább JSON végpontokra építs.

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
| Kontroller method írása | legacy `Framework\Request` / `Response` (a Dispatcher ezt adja át) |
| JSON válasz | `Response::json([...])` |
| HTML válasz | `$this->view('template.twig', [...])` *(M2.d-ig)* |
| Body hozzáférés middleware-ben | `$request->getParsedBody()` (form) vagy `(string) $request->getBody()` (raw) |
| Header hozzáférés middleware-ben | `$request->getHeaderLine('X-Header')` |
