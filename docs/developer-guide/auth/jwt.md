# JWT és TokenService

A `Framework\Auth\TokenService` az auth réteg központi service-e. Felelős az access token kiállításáért, verifikálásáért, és (a refresh tokenek mellett) a teljes login szekció kezelésért.

## TokenService felépítése

```php
new TokenService(
    jwt: $config,                  // Lcobucci\JWT\Configuration (RS256-tal)
    refreshTokens: $repository,    // RefreshTokenRepository (PDO)
    clock: $clock,                 // PSR-20 ClockInterface
    issuer: 'antarctic',
    audience: 'antarctic-spa',
    accessTtl: 900,                // másodperc — 15 perc
    refreshTtl: 2592000,           // másodperc — 30 nap
    clockSkew: 5,                  // tolerált órabütyök
);
```

Tipikusan a Bootstrap-ben (vagy később a DI container-ben) építed:

```php
use Framework\Auth\JwtConfigFactory;
use Framework\Auth\RefreshTokenRepository;
use Framework\Auth\SystemClock;
use Framework\Auth\TokenService;
use Framework\Dal;

$jwtConfig = require ROOT_PATH . '/config/jwt.php';
$tokenService = new TokenService(
    jwt: JwtConfigFactory::create($jwtConfig),
    refreshTokens: new RefreshTokenRepository(Dal::getConnection()),
    clock: new SystemClock(),
    issuer: $jwtConfig['issuer'],
    audience: $jwtConfig['audience'],
    accessTtl: $jwtConfig['access_ttl'],
    refreshTtl: $jwtConfig['refresh_ttl'],
    clockSkew: $jwtConfig['clock_skew'],
);
```

!!! info "M3.c után"
    A DI container (php-di) ezt automatikusan összerakja autowiringgel — ezt a manuális összeszerelést onnantól nem kell magadnak megírnod.

## Access token kiállítása

```php
$jwt = $tokenService->issueAccessToken(userId: 42, roles: ['admin', 'editor']);
// → "eyJ0eXAiOiJKV1Q...".
```

A token claims:

```json
{
  "iss": "antarctic",
  "aud": ["antarctic-spa"],
  "jti": "9f2c5a1e6b...",
  "iat": 1746000000,
  "exp": 1746000900,
  "sub": "42",
  "roles": ["admin", "editor"]
}
```

**Mit ne tegyél bele**: PII (név, email, születési dátum). A token base64-kódolt, **nem titkosított** — bárki, aki látja, ki tudja olvasni. A `roles` lista végrehajtáshoz kell, az minimális info amit nem érdemes újra-fetch-elni.

## Access token verifikálása

```php
use DomainException;

try {
    $token = $tokenService->verifyAccess($jwt);
    $userId = (int) $token->claims()->get('sub');
    $roles = $token->claims()->get('roles');
} catch (DomainException $e) {
    // $e->getCode() === 401
    // ok lehet: lejárt, malformed, rossz issuer/audience, rossz signature
}
```

A `verifyAccess()` minden hiba esetén `DomainException`-t dob `code=401`-gyel. A típushoz nem kell illeszteni — a `code` és a `getMessage()` ad info-t.

Ellenőrzött constraint-ek:

1. **`SignedWith`** — a verifikációs kulccsal megegyező aláírás.
2. **`LooseValidAt`** — `exp` és `nbf` claim a clock skew (5s) ablakon belül.
3. **`IssuedBy`** — `iss === $issuer`.
4. **`PermittedFor`** — `aud` tartalmazza az `$audience`-t.

!!! tip "Algoritmus-confusion támadás"
    A `JwtConfigFactory` explicit RS256-ra van rögzítve. A `none` és HS256 attack vektor automatikusan blokkolva van, mert a verifikátor csak az RS256-os kulcsot fogadja el.

## Tokenből usert tölteni

A token csak `sub` (userId) és `roles` mezőket tartalmaz. Ha tovább kell az user objektum:

```php
$token = $tokenService->verifyAccess($jwt);
$userId = (int) $token->claims()->get('sub');

$user = $userRepository->findById($userId);
if ($user === null) {
    throw new DomainException('User not found.', 401);
}
```

A friss DB lookup biztosítja, hogy a deaktivált usereket azonnal kilőjük (a JWT még érvényes lehet, de a session már nem).

!!! note "M2.b-ben automatizálva"
    Az `AuthMiddleware` ezt magától megteszi: a token verify után az `$user`-t a request attribútumba teszi, és a `#[RequireAuth]`-os kontroller method-ok tisztán hozzáférhetnek.

## Refresh token kiállítása

A login flow utolsó lépésében:

```php
$refresh = $tokenService->issueRefreshToken(
    userId: $user->id,
    userAgent: $_SERVER['HTTP_USER_AGENT'] ?? null,
    ip: $_SERVER['REMOTE_ADDR'] ?? null,
);

// $refresh = [
//   'token'      => 'aBcDe…',
//   'family_id'  => '9f2c…',
//   'expires_at' => DateTimeImmutable
// ]
```

A `token`-t cookie-ba teszed (httpOnly Secure SameSite=Strict). A `family_id` és `expires_at` audit-célra; a kliensnek sosem küldöd át.

## Refresh token rotáció

A `rotateRefresh()` egy híváson belül:

1. Megkeresi a `token` hash-ét a DB-ben.
2. Validál: létezik, nem revokált, nem járt le, ugyanaz a user.
3. **Revokálja a régi tokent** (`revoked_at = NOW`).
4. **Új refresh tokent** ad, **ugyanazzal a `family_id`-vel**, `rotated_from = régi_id`-vel.
5. **Új access tokent** is kiállít.

```php
$rotated = $tokenService->rotateRefresh(
    refreshToken: $cookieValue,
    userId: $userId,
    roles: $user->getRoles(),
    userAgent: $_SERVER['HTTP_USER_AGENT'] ?? null,
    ip: $_SERVER['REMOTE_ADDR'] ?? null,
);

// $rotated = [
//   'access_token'  => 'eyJ…',
//   'refresh_token' => 'XyZqW…',
//   'expires_at'    => DateTimeImmutable
// ]
```

Reuse detection részletei: [Refresh token rotáció](refresh-tokens.md).

## Revoke (logout)

```php
$tokenService->revokeRefresh($cookieValue);
```

Idempotens: ha a token már revokált vagy nem létezik, néma no-op. A logout endpoint ezt hívja, majd kitörli a `__Host-refresh` cookie-t.

## Tesztelési tippek

- **Determinisztikus időkezelés**: használj `FrozenClock`-ot (lásd a `TokenServiceTest`-ben), így az `iat`/`exp` claim-ek számszerűen ellenőrizhetők.
- **On-the-fly kulcsgen**: 2048 bit elég tesztre, néhány század másodperc generálás.
- **sqlite in-memory** a `RefreshTokenRepository`-hoz — a séma kompatibilis (lásd a tesztfájlokban).

```php
$clock = new FrozenClock(new DateTimeImmutable('2026-01-01T12:00:00+00:00'));
$service = new TokenService(/* ..., clock: $clock, ... */);

// Lejárat tesztelése
$jwt = $service->issueAccessToken(1);
$clock->set(new DateTimeImmutable('2026-01-01T13:00:00+00:00'));
$service->verifyAccess($jwt);  // → DomainException(401)
```
