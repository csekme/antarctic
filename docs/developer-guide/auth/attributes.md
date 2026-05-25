# `#[RequireAuth]` és `#[RequireRole]`

Egy kontroller method-ra vagy osztályra tett **PHP attribútum** jelzi a Dispatchernek, hogy a végpontnak autentikációt és/vagy bizonyos szerepet kell követelnie. Egyik sem alterálja a kontroller kódot — a deklaratív policy a method/class fölött áll.

## `#[RequireAuth]`

```php
use Framework\Auth\RequireAuth;
use Framework\Path;
use Framework\Response;

class ProfileController extends AbstractController
{
    #[Path(path: '/api/v1/profile', method: 'GET')]
    #[RequireAuth]
    public function show(): Response
    {
        $user = $this->request->authUser;   // AuthenticatedUser
        return Response::json([
            'userId' => $user->id,
            'roles'  => $user->roles,
        ]);
    }
}
```

**Mit csinál a Dispatcher**:

1. Az `AuthMiddleware` (a pipeline-ban előbb) megpróbálja parse-olni a Bearer tokent. Sikernél `AuthenticatedUser`-t tesz a request attribútumba; sikertelennél `unauthenticated_reason` jelzi az okot.
2. A `#[RequireAuth]` jelenlétét észlelve a Dispatcher ellenőrzi a `request->authUser`-t. Ha üres → 401 + a rögzített ok (`expired`, `malformed_authorization_header`, stb.).
3. Csak ezután fut le a kontroller method.

A 401 választ az `ErrorHandlerMiddleware` formázza `application/problem+json`-né (lásd [Hibakezelés](../http/error-handling.md)).

## `#[RequireRole]`

```php
use Framework\Auth\RequireRole;

class AdminController extends AbstractController
{
    #[Path(path: '/api/v1/admin/users', method: 'GET')]
    #[RequireRole('admin')]
    public function listUsers(): Response { … }

    #[Path(path: '/api/v1/admin/reports', method: 'GET')]
    #[RequireRole('admin', 'auditor')]   // bármelyik szerep elég
    public function reports(): Response { … }
}
```

Több role: variadic argumentumok, bármelyik elég (OR logika). Két különálló role-konjukció (AND) esetén tegyél ki két attribútumot — repeatable:

```php
#[RequireRole('admin')]
#[RequireRole('billing')]
public function reset(): Response { … }
```

**Logika**:

- `#[RequireRole]` **implikálja a `#[RequireAuth]`-ot**. Ha nincs autentikált user → 401 (még a 403 előtt).
- Ha van user, de **egyik várt role-ja sincs** → 403 + `User does not have the required role.`

## Osztály-szintű attribútum

Az attribútumok class targetre is alkalmazhatók:

```php
#[RequireAuth]
class ProfileController extends AbstractController
{
    #[Path(path: '/api/v1/profile', method: 'GET')]
    public function show(): Response { … }

    #[Path(path: '/api/v1/profile', method: 'PUT')]
    public function update(): Response { … }
}
```

A Dispatcher először az osztály-szintű attribútumokat futtatja le, majd a method-szintűeket. Ha mindkettő szigorúbb (pl. class `#[RequireAuth]`, method `#[RequireRole('admin')]`), a szigorúbb effektíven érvényesül.

## Az `AuthenticatedUser`

A request attribútumon átadott objektum:

```php
final class AuthenticatedUser
{
    public function __construct(
        public readonly int $id,
        public readonly array $roles = [],
        public readonly ?string $jti = null,
    ) {}

    public function hasRole(string $role): bool;
    public function hasAnyRole(array $roles): bool;
}
```

A `id` a JWT `sub` claim-je castelt-elve. A `roles` a `roles` claim-ből; a `jti` a `jti` claim — audit naplózáshoz használható.

A controllerből:

```php
$user = $this->request->authUser;
if ($user->hasRole('admin')) { … }
```

!!! warning "Ne kérd le DB-ből, ha nem kell"
    A JWT 15 perces TTL alatt érvényes. Ha minden requestre DB lookup-pal frissíted a usert, gyakorlatilag visszahoztad a session overheadet. Csak akkor lookup, ha **friss adatra** van szükséged (pl. profil oldal). Az auth és authz döntéseket a JWT claim-jeire alapozva intézd.

## Anonymous Bearer pass-through

Ha egy endpoint csak **opcionálisan** autentikál (publikus, de bejelentkezve más a viselkedés), ne tegyél rá `#[RequireAuth]`-ot. Az `AuthMiddleware` akkor is parse-olja a Bearer-t, ha jön; csak nem kötelezi.

```php
#[Path(path: '/api/v1/feed', method: 'GET')]
public function feed(): Response
{
    $user = $this->request->authUser;   // ?AuthenticatedUser
    if ($user !== null) {
        // személyre szabott feed
    } else {
        // publikus feed
    }
    …
}
```

## Hogyan futnak a két középrétegen keresztül

```
Request
   ↓
AuthMiddleware                            (PSR-15 / pipeline)
  ↳ parse Bearer? OK    → request->withAttribute('user', AuthenticatedUser)
  ↳ parse Bearer? FAIL  → request->withAttribute('unauthenticated_reason', '…')
  ↳ nincs Bearer        → változatlan
   ↓
LegacyDispatcherMiddleware
  ↳ HttpAdapter::toLegacyRequest → legacy Request->authUser + ->unauthenticatedReason
   ↓
Dispatcher::processAnnotation             (per-route attribútumok)
  ↳ #[RequireAuth]  →  ha authUser=null  → throw Exception('…', 401)
  ↳ #[RequireRole]  →  401, ha nincs user; 403, ha nincs role
   ↓
Controller method futás
```

A `Throwable` az `ErrorHandlerMiddleware`-en csapódik le, ami a HTTP status code-ot és problem+json envelope-ot előállítja.

## Tesztelés

A kontroller method-szintű attribútum-feldolgozást a `RequireAuthIntegrationTest` ellenőrzi. Nem kell teljes pipeline-t felépíteni; reflection-en keresztül közvetlenül a `Dispatcher::processAnnotation()` viselkedését lehet tesztelni.

```php
$reflection = new ReflectionMethod(YourController::class, 'yourMethod');
$attribute = $reflection->getAttributes(RequireAuth::class)[0] ?? null;

$request = new Request('', 'GET', [], [], [], [], []);
$request->authUser = new AuthenticatedUser(id: 1, roles: ['user']);

// invokálás reflection-on át (a metódus private)
```

Példa: [`tests/Framework/Auth/RequireAuthIntegrationTest.php`](https://github.com/csekme/antarctic/blob/main/src/tests/Framework/Auth/RequireAuthIntegrationTest.php).
