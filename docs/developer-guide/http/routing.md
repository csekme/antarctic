# Routing

Az Antarctic **attribútum-alapú** routinggal dolgozik. A kontroller metódusaira `#[Path(...)]` attribútumot teszel, és a `ClassExploder` a startupkor scanneli az osztályokat, hogy felépítse a routing táblát.

!!! info "Aktuális állapot (M3.b óta)"
    A `ClassExploder` **reflection-alapú** — a Composer autoloader class-map-jét és PSR-4 prefixeit használva állítja össze a controller-listát. A `Router::match()` **method-aware**: a URL-re illeszkedő, de rossz HTTP-metódusú route 405-öt ad, nem 404-et. Production-időben a route-tábla előre cache-elhető (`bin/console route:cache`).

## Az `#[Path]` attribútum

```php
namespace Framework;

#[\Attribute]
class Path {
    public function __construct(
        public ?string $path = null,
        public ?string $method = null,
    ) {}
}
```

## Egyszerű végpont

```php
use Framework\AbstractController;
use Framework\Path;
use Framework\Response;

class HelloController extends AbstractController
{
    #[Path(path: '/api/v1/hello', method: 'GET')]
    public function index(): Response
    {
        return Response::json(['ok' => true]);
    }
}
```

## URL paraméterek

A `{name}` és `{name:regex}` szintaxis támogatott:

```php
#[Path(path: '/api/v1/users/{id:\d+}', method: 'GET')]
public function show(int $id): Response
{
    // $id kötve a path paraméterhez
    return Response::json(['userId' => $id]);
}
```

Példák:

| Path attribútum | Match |
|---|---|
| `/api/v1/users/{id:\d+}` | `/api/v1/users/42` (de **nem** `/api/v1/users/abc`) |
| `/api/v1/posts/{slug}` | bármilyen karakter a `/` jelig |
| `/posts/{controller}/{action}` | dinamikus dispatch (legacy minta) |

## HTTP method

A `method` mező kötelezően egyet enged be ezek közül: `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`.

```php
#[Path(path: '/api/v1/users', method: 'POST')]
public function create(): Response { ... }

#[Path(path: '/api/v1/users/{id:\d+}', method: 'DELETE')]
public function delete(int $id): Response { ... }
```

### 405 vs 404 megkülönböztetés

A `Router::match($url, $method)` egy `MatchResult` value object-tel tér vissza, három állapottal:

- `found($params)` — az URL és a metódus is illeszkedik.
- `methodNotAllowed($allowedMethods)` — az URL illeszkedik, de más HTTP-metódussal. A Dispatcher 405-öt dob, és az `$allowedMethods` lista alapján generálható `Allow:` header.
- `notFound()` — semmi nem illeszkedik az URL-re.

```php
// Példa: /api/v1/articles + PUT, ha csak GET van regisztrálva
HTTP/1.1 405 Method Not Allowed
Allow: GET
```

## Hova helyezd a kontrollert

A `ClassExploder` két helyet vizsgál:

- `src/Application/Controllers/` → `Application\Controllers\` namespace
- `src/Framework/Controllers/` → `Framework\Controllers\` namespace (csak ha `Config::useCoreController()` `true`)

A te app-od kontrollerei az `Application\Controllers\` alá kerüljenek. A `Framework\Controllers\` ma már csak az API-controllereket tartalmazza (pl. `Framework\Controllers\Api\V1\AuthController` — M2.d óta a legacy Twig UI törölve van).

!!! tip "Class-szintű `#[Path]` opcionális"
    Ha a controllerednek nincs class-szintű `#[Path]`-ja (csak method-szintűek vannak), a `ClassExploder` egy belső sentinel-kulccsal regisztrálja, és a method path-ok adják a teljes route-ot. Például az `AuthController` osztály-szinten nincs prefixelve, és a `#[Path(path: '/api/v1/auth/login', method: 'POST')]` method-szintű attribútum adja a teljes URL-t.

## Route cache (production)

Dev-időben minden requesten reflection-szel scanneljük a controllereket. Production deploy lépésben érdemes előre cache-elni:

```bash
composer install --no-dev --optimize-autoloader
bin/console route:cache
# → Wrote N route(s) to var/cache/routes.php
```

A `Bootstrap.php` `RouteCache::load()`-dal próbálkozik betölteni a cache-t — ha létezik a fájl, nincs reflection-cost; ha nincs, scan minden requesten.

```bash
bin/console route:cache --clear   # invalidate (dev-rebuilding)
```

A cache-fájl verzióelt (`RouteCache::VERSION`), így a séma-változások automatikusan invalidálódnak. A `/src/var/` egész mappa gitignore-olt.

## API namespace konvenció

Az `/api/v1/*` prefix konvenció (nem kötelező a router-en, de a `ContentNegotiation::wantsJson()` minden `/api/*` path-ot JSON-ként kezel). Új endpointok így nevezendők:

| ✅ Jó | ❌ Kerülendő |
|---|---|
| `/api/v1/users` | `/users` |
| `/api/v1/auth/login` | `/login` |
| `/api/v1/orders/{id:\d+}` | `/orders/{id:\d+}` |

A `ContentNegotiation::wantsJson()` automatikusan JSON-választ ad bármi `/api/*` path alatt, függetlenül az `Accept` headertől.

## Több attribútum / class-prefix

A `#[Path(path: '/api/v1/users')]` osztály-szinten + method-szintű al-pathok kombináció támogatott:

```php
#[Path(path: '/api/v1/users')]
class UserController extends Controller
{
    #[Path(method: 'GET')]                          // → GET /api/v1/users
    public function index(): Response { ... }

    #[Path(path: '/{id:\d+}', method: 'GET')]       // → GET /api/v1/users/42
    public function show(int $id): Response { ... }
}
```

Ha a class-szintű `#[Path]` hiányzik, a method-szintű attribútumok adják a teljes URL-t (lásd az `AuthController` mintát).

## Method-paraméter feloldás

A jelenlegi Dispatcher így hidratálja a kontroller action paramétereit:

- **GET** request 1-paraméteres metóduson → a path változó értékét adja át (ld. `users/{id:\d+}` → `$id`).
- **Nem-GET** request 1-paraméteres metóduson → a `$_POST` tömböt adja át.
- 0 paraméter → nincs argumentum.

```php
// Path: /api/v1/users/{id:\d+}, GET → $id = "42"
public function show(int $id): Response { ... }

// Path: /api/v1/users, POST → $body = ['name' => 'Alice', ...]
public function create(array $body): Response { ... }
```

!!! info "Jövőbeli DTO-hidrátálás (M4.b)"
    A `symfony/validator` integráció után az action paramétereken DTO típushintet adhatsz, és a `ControllerDispatcher` automatikusan deserializálja + validálja a JSON body-t.

## Routing tesztelése

A `StandardRouterImpl::match($url)` egy URL-re ad vissza paramétereket vagy `false`-ot:

```php
$router = new StandardRouterImpl();
$result = $router->match('api/v1/users/42');
// → ['controller' => 'UserController', 'namespace' => 'Application\\Controllers',
//    'action' => 'show', 'method' => 'GET', 'id' => '42']
```

A test példa: [`tests/Framework/Routing/`](https://github.com/csekme/antarctic/tree/main/src/tests/Framework/Routing) *(M3.b után gyarapodik)*.
