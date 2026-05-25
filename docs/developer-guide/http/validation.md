# Validáció (Request DTO-k)

Az `/api/v1/*` endpointok bemenetét a `Dispatcher` automatikusan **hidratálja DTO objektumokba** és a [`symfony/validator`](https://symfony.com/doc/current/components/validator.html) attribútum-alapú kényszereken keresztül futtatja. Hibás bemenet → 422 [problem+json](error-handling.md) az `errors` field-listával.

## Egy DTO írása

A DTO egy plain PHP osztály, amely megvalósítja a `Framework\Validation\Validatable` marker interfészt, és a property-ket konstruktor-promotált `readonly` paraméterekként deklarálja `Assert\*` attribútumokkal:

```php
<?php

namespace Application\Dto;

use Framework\Validation\Validatable;
use Symfony\Component\Validator\Constraints as Assert;

final class LoginRequest implements Validatable
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required.')]
        #[Assert\Email(message: 'Email must be a valid address.')]
        public readonly string $email = '',
        #[Assert\NotBlank(message: 'Password is required.')]
        public readonly string $password = '',
    ) {}
}
```

A defaultok azért fontosak, mert a hidratátor hiányzó payload-kulcsra azokat használja, és a `NotBlank` constraint dobja el utólag — így a hibaüzenetek a te `message` meződ szerint érkeznek.

## Controller action

A `Dispatcher` annak alapján dönt, hogy a controller-paraméter típusa megvalósítja-e a `Validatable`-t. Ha igen, a JSON body-t bevonja és validálja, mielőtt az actiont meghívná:

```php
#[Path(path: '/api/v1/auth/login', method: 'POST')]
public function login(LoginRequest $body): Response
{
    $user = User::authenticate($body->email, $body->password);
    // ...
}
```

A controller a sikeres ágra koncentrálhat — ha eljut a kódig, a `$body` invariánsai teljesülnek.

## A 422 válasz

Hibás bemenet:

```http
POST /api/v1/auth/login HTTP/1.1
Content-Type: application/json

{}
```

```http
HTTP/1.1 422 Unprocessable Entity
Content-Type: application/problem+json; charset=utf-8

{
  "type": "about:blank",
  "title": "Unprocessable Entity",
  "status": 422,
  "detail": "The request body failed validation.",
  "instance": "/api/v1/auth/login",
  "errors": {
    "email":    ["Email is required."],
    "password": ["Password is required."]
  }
}
```

Az `errors` map kulcsai a property-path (azaz a DTO property neve), értékei az adott property összes constraint-hibájának listája. A kliens egy formon közvetlenül kiterítheti a mezőhibákat.

## Mit ellenőriz a hidratátor

A hidratátor (`Framework\Validation\RequestHydrator`) a `symfony/validator` előtt két plusz lépést végez:

1. **Típus-illeszkedés** — a payload kulcsának értéke a paraméter típusához passzol-e (`string` ↔ `string`, `int` ↔ `int`, `array` ↔ `array`, class ↔ `instanceof`). Eltérés esetén `This field must be of type X.` hiba.
2. **Hiányzó kötelező mezők** — ha nincs sem payload-kulcs, sem default, sem nullable → `This field is required.`.

Csak ha mindkét lépés tiszta, akkor fut a `symfony/validator`. Így a validátor sosem szembesül egy `null`-lá kényszerített objektummal vagy egy `TypeError`-t dobó konstruktorral.

## Egyéni típusok

A DTO mezői lehetnek skalárok, tömbök vagy más osztályok is. Bonyolult, beágyazott DTO-k esetén:

- A `symfony/validator` natívan ismeri a `#[Assert\Valid]` kaszkádot — ezt a hidratátor önmaga **nem** hajtja végre, mert a `Validatable` cél osztály konstruktora maga felelős a részstruktúrák felépítéséért.
- Egyszerű skalár-listák (`list<string>`) az `array` típust kapják + `#[Assert\All]` constraint a tartalomra.

A `Validatable` interfésznek nincs metódus-szerződése — ha bonyolult dolgokat akarsz csinálni hidratáció után, írj `__construct` body-ba normalizációt (pl. lowercase email).

## Tesztelés

A DTO + hidratátor unit-tesztelhető a `RequestHydrator` direkt példányosításával:

```php
$hydrator = new RequestHydrator();

$dto = $hydrator->hydrate(LoginRequest::class, [
    'email' => 'alice@example.com',
    'password' => 'hunter2',
]);

$this->assertSame('alice@example.com', $dto->email);
```

Hibás bemenetre `Framework\Validation\ValidationException` dobódik, `getErrors()` visszaadja a problem+json `errors` mezőjét.

## A réteg határai

- **CSRF / Bearer auth** — middleware réteg felel értük (`AuthMiddleware`, `Dispatcher::crossSiteRequestForgeryProtection`).
- **Rate limit** — külön middleware, lásd [Rate limit](rate-limit.md).
- **OpenAPI generálás** — külön réteg, lásd [OpenAPI](openapi.md). Az `Assert\*` attribútumok és az `OA\Property` attribútumok közös DTO-n élnek meg, így a kétfajta annotáció jól együttműködik.
