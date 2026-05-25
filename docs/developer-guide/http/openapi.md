# OpenAPI + Swagger UI

A backend self-documenting — minden `/api/v1/*` endpoint a forráskódja mellett deklarálja az OpenAPI-kontraktusát PHP attribútumokkal, és két végpont szolgálja ki:

| Endpoint | Mit ad |
|---|---|
| `GET /api/v1/docs.json` | OpenAPI 3.0 JSON spec |
| `GET /api/v1/docs`      | Swagger UI HTML (dev) — production-ben 404 |

A scannert a [`zircote/swagger-php`](https://zircote.github.io/swagger-php/) szállítja; a típus-rendszert a [`OpenApi\Attributes`](https://github.com/zircote/swagger-php/tree/master/src/Attributes) névtér adja.

## Egy endpoint dokumentálása

Két dolog kell: a DTO-n schema-megjelölés, az action-en operation-megjelölés.

### DTO oldal

```php
namespace Application\Dto;

use Framework\Validation\Validatable;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: 'LoginRequest',
    required: ['email', 'password'],
)]
final class LoginRequest implements Validatable
{
    public function __construct(
        #[Assert\NotBlank, Assert\Email]
        #[OA\Property(type: 'string', format: 'email', example: 'alice@example.com')]
        public readonly string $email = '',
        #[Assert\NotBlank]
        #[OA\Property(type: 'string', format: 'password')]
        public readonly string $password = '',
    ) {}
}
```

A `Symfony\Assert\*` és `OA\Property` jelölések egymást kiegészítik — az egyik a futási validációhoz, a másik a dokumentációhoz. (Egy jövőbeli M-ben automatizálható lesz a kettő közötti bridge, jelenleg duplán deklaráljuk.)

### Controller oldal

```php
use OpenApi\Attributes as OA;

#[Path(path: '/api/v1/auth/login', method: 'POST')]
#[OA\Post(
    path: '/api/v1/auth/login',
    summary: 'Authenticate with email + password.',
    tags: ['auth'],
    security: [],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')),
    responses: [
        new OA\Response(response: 200, description: 'Access + refresh issued.'),
        new OA\Response(response: 422, description: 'Body failed validation.'),
    ],
)]
public function login(LoginRequest $body): Response
{
    // ...
}
```

A `#[Path]` (Antarctic-saját routing) és `#[OA\Post]` (swagger-php) elkülönül szándékosan — előbbi a dispatcher-hez, utóbbi a dokumentációhoz. A két `path` paramétert szinkronban kell tartani; jövőbeli optimalizáció lehet, hogy az `#[OA\Post]` ezt a `#[Path]`-ból olvassa.

## Root metaadat

A top-level `#[OA\OpenApi]` (cím, verzió, szerverek, biztonsági sémák) a [`Framework\OpenApi\OpenApiInfo`](../../../src/Framework/OpenApi/OpenApiInfo.php) üres osztályon él. Új API-szintű deklaráció (pl. egy új tag, egy global parameter) itt landol.

## Cache + production

Dev-ben a `/api/v1/docs.json` request-időben scanneli a forrást (~50–200 ms). Production deploy-flow:

```bash
bin/console route:cache
bin/console openapi:dump      # var/cache/openapi.json (~7 kB)
```

A `DocsController` ezután a fájlt olvassa (`file_get_contents`, ~1 ms). A `var/cache/` mappa gitignore-olt; minden deploy újra generálja. A `openapi:dump --clear` a fájl törlésére jó.

## Swagger UI

A `/api/v1/docs` HTML oldal a [`unpkg.com/swagger-ui-dist@5`](https://www.npmjs.com/package/swagger-ui-dist) CDN-bundle-jét tölti be — nem szállítunk lokális JS/CSS asset-et. Az oldal csak akkor érhető el, ha `APP_ENV != production` (egyébként 404 problem+json), hogy a Swagger UI ne legyen publikus felület prodban.

Ha production-ben is kellene a UI (pl. partner integrátoroknak), a `DocsController::setUiEnabled(true)`-t a controller `__construct` body-jában elhelyezve felülbírálható — vagy egy follow-up PR-ban egy `APP_OPENAPI_UI=1` env változót vezethetünk be.

## Tesztelés

A scanner unit-tesztelhető a `OpenApiGenerator` direkt példányosításával:

```php
$generator = OpenApiGenerator::forSource(__DIR__ . '/../../..');
$doc = json_decode($generator->scan(), true);

$this->assertArrayHasKey('/api/v1/auth/login', $doc['paths']);
$this->assertContains('email', $doc['components']['schemas']['LoginRequest']['required']);
```

A cache-fájlos ágat egy `new OpenApiGenerator([], $tmpFile)` + `file_put_contents($tmpFile, '...')` kombóval lehet tesztelni; lásd [`OpenApiGeneratorTest`](../../../src/tests/Framework/OpenApi/OpenApiGeneratorTest.php).

## Mit nem kezel

- **Response body schemák** — a 200 válaszok struktúrája jelenleg csak `description`-ban van; külön `AccessTokenResponse` DTO-k a M4.b.3 (`{data, meta}` envelope) után jönnek.
- **openapi-typescript generálás kliens oldalon** — a React example (M6) `package.json` script-jeként landol.
- **Auto-bridge `Symfony\Assert\*` → `OA\*`** — jelenleg duplán deklaráljuk; bridge később jöhet.
