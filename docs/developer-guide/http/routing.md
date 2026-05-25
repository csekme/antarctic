# Routing

Az Antarctic **attribútum-alapú** routinggal dolgozik. A kontroller metódusaira `#[Path(...)]` attribútumot teszel, és a `ClassExploder` a startupkor scanneli az osztályokat, hogy felépítse a routing táblát.

!!! info "Készülő változások"
    A jelenlegi routing **regex-alapú** scanningot használ. Az M3.b PR-ben reflection-alapúra cseréljük, és bekerül a method-aware 405 vs 404 kezelés is. Az `#[Path]` attribútum API maga **nem** változik.

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

!!! warning "Egyelőre nincs 405"
    A jelenlegi Router 404-et ad, ha az URL nem matchel egyetlen útvonalra sem **akkor is**, ha matchelne egy másik HTTP method-dal. Az M3.b ezt korrigálja: ha az URL match-el, de a method nem, 405 Method Not Allowed-ot kapsz.

## Hova helyezd a kontrollert

A `ClassExploder` két helyet vizsgál:

- `src/Application/Controllers/` → `Application\Controllers\` namespace
- `src/Framework/Controllers/` → `Framework\Controllers\` namespace (csak ha `Config::useCoreController()` `true`)

A te app-od kontrollerei az `Application\Controllers\` alá kerüljenek. A `Framework\Controllers\` jelenleg legacy session-alapú UI-t tartalmaz (login, signup, 2fa), ami **M2.d-ben törlésre kerül**.

## API namespace konvenció

A jövőbeli verziókban (M3.a) az `/api/v1/*` prefix lesz **kötelező** minden REST endpointra. Már most érdemes így nevezni az új végpontokat:

| ✅ Jó | ❌ Kerülendő |
|---|---|
| `/api/v1/users` | `/users` |
| `/api/v1/auth/login` | `/login` |
| `/api/v1/orders/{id:\d+}` | `/orders/{id:\d+}` |

A `ContentNegotiation::wantsJson()` automatikusan JSON-választ ad bármi `/api/*` path alatt, függetlenül az `Accept` headertől.

## Több attribútum egy osztályon

!!! note "Egyelőre nem támogatott"
    Több `#[Path]` egy osztályon, vagy class-szintű path prefix az M3.b-ben jön be (`#[Path(path: '/api/v1/users')]` osztály-szinten, egyedi sub-pathok a metódusokon).

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
