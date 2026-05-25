# Auth endpointok

Az Antarctic öt auth végpontot szállít az `/api/v1/auth/*` prefix alatt. Bejövő kliens akár SPA, akár mobil-app — mindegyikkel ugyanaz a flow.

| Method | URL | Mi kell | Mi jön vissza |
|---|---|---|---|
| `POST` | `/api/v1/auth/login` | `{email, password}` JSON | access + refresh + csrf, **vagy** 2FA challenge token |
| `POST` | `/api/v1/auth/2fa/verify` | `{challenge_token, code}` JSON | access + refresh + csrf |
| `POST` | `/api/v1/auth/refresh` | refresh cookie + `X-Csrf-Token` header | új access + új refresh + új csrf |
| `POST` | `/api/v1/auth/logout` | refresh cookie | `{ok: true}` |
| `GET` | `/api/v1/auth/me` | `Authorization: Bearer …` | aktuális user |

## `POST /api/v1/auth/login`

Email + jelszó alapú bejelentkezés. Sikeresnél kiállítja az access tokent (JSON-ban), a refresh tokent (`__Host-refresh` httpOnly cookie) és a CSRF tokent (`csrf_token` cookie + JSON).

**Kérés**:

```http
POST /api/v1/auth/login HTTP/1.1
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "s3cret"
}
```

**Siker (200)**:

```json
{
  "access_token": "eyJ0eXAi…",
  "token_type": "Bearer",
  "expires_in": 900,
  "csrf_token": "9f2c5a1e…",
  "user": {
    "id": 42,
    "email": "user@example.com",
    "username": "alice",
    "roles": ["editor"]
  }
}
```

**Headerek**:

```http
Set-Cookie: __Host-refresh=…; Max-Age=2592000; Path=/api/v1/auth; SameSite=Strict; Secure; HttpOnly
Set-Cookie: csrf_token=9f2c5a1e…; Max-Age=2592000; Path=/; SameSite=Strict; Secure
```

**Hibák**:

| Status | Helyzet |
|---|---|
| 400 | Hiányzó email vagy password mező |
| 401 | Rossz email vagy jelszó |

A válasz `application/problem+json` formátumú:

```json
{ "type": "about:blank", "title": "Unauthorized", "status": 401, "detail": "Invalid credentials." }
```

### 2FA-engedélyezett user

Ha a usernek van `enabled=1` 2FA bejegyzése (`two_factor` tábla, `method='app'`), a `/login` **nem** ad access tokent és **nem** állít be refresh cookie-t. Helyette egy rövid életű challenge tokent ad:

**Siker (200)**:

```json
{
  "requires": "2fa",
  "challenge_token": "eyJ0eXAi…",
  "methods": ["app"],
  "expires_in": 300
}
```

A kliensnek a `challenge_token`-t és a TOTP kódot a `/2fa/verify`-re kell elküldenie 5 percen belül.

## `POST /api/v1/auth/2fa/verify`

Befejezi a két-lépcsős loginot. A `challenge_token`-t a `/login` válaszából kapta a kliens; a `code` a TOTP-app (Google Authenticator, 1Password, stb.) aktuális 6 számjegye.

**Kérés**:

```http
POST /api/v1/auth/2fa/verify HTTP/1.1
Content-Type: application/json

{
  "challenge_token": "eyJ0eXAi…",
  "code": "123456"
}
```

**Siker (200)**: ugyanaz a payload mint a `/login` 2FA-mentes ágánál — `access_token`, `expires_in`, `csrf_token`, `user`, plusz `Set-Cookie` refresh + csrf.

**Hibák**:

| Status | Helyzet |
|---|---|
| 400 | Hiányzó `challenge_token` vagy `code` |
| 401 | Lejárt / hamis / nem-challenge célú token |
| 401 | User már inaktív vagy törölt |
| 401 | A user 2FA-ja közben kikapcsolódott |
| 401 | Rossz TOTP kód |

!!! info "A challenge token önmagában nem jogosít"
    A `challenge_token` egy RS256 JWT, ugyanazokkal a kulcsokkal aláírva mint az access token, de **`purpose: "2fa_challenge"`** claim-mel. A `TokenService::verifyAccess` nem fogadja el (mert nincs `purpose=access` szándék-eldöntés rajta), és a `TwoFactorChallengeService` szigorúan ellenőrzi a purpose-t — egy ellopott challenge token önmagában nem ad erőforrás-hozzáférést.

## `POST /api/v1/auth/refresh`

Új access tokent generál, rotálja a refresh tokent. **Double-submit CSRF** ellenőrzéssel.

**Kérés** (a kliens nem küld body-t; a böngésző automatikusan elküldi a cookie-kat):

```http
POST /api/v1/auth/refresh HTTP/1.1
Cookie: __Host-refresh=…; csrf_token=9f2c5a1e…
X-Csrf-Token: 9f2c5a1e…
```

A `X-Csrf-Token` header **kötelező**, és pontosan egyeznie kell a `csrf_token` cookie értékével (`hash_equals()`-szel ellenőrzött). Ez a védelem az ellen, hogy egy másik origin script triggereljen automatikus refresh-t.

**Siker (200)**:

```json
{
  "access_token": "eyJ0eXAi…",
  "token_type": "Bearer",
  "expires_in": 900,
  "csrf_token": "új-token"
}
```

A response új `Set-Cookie`-kat ad mindkét cookie-ra (rotation).

**Hibák**:

| Status | Helyzet |
|---|---|
| 401 | Hiányzó refresh cookie |
| 401 | Ismeretlen / lejárt / revokált refresh token |
| 401 | **Reuse detected** — a teljes család (`family_id`) revokált; a kliensnek újra be kell jelentkeznie. |
| 403 | CSRF token mismatch (cookie ≠ header) |

## `POST /api/v1/auth/logout`

Revokálja a refresh tokent és kitörli a cookie-kat. Idempotens — ismeretlen vagy lejárt token esetén is 200-at ad.

**Kérés**:

```http
POST /api/v1/auth/logout HTTP/1.1
Cookie: __Host-refresh=…
```

**Válasz (200)**:

```json
{ "ok": true }
```

```http
Set-Cookie: __Host-refresh=; Max-Age=0; Path=/api/v1/auth; SameSite=Strict; Secure; HttpOnly
Set-Cookie: csrf_token=; Max-Age=0; Path=/; SameSite=Strict; Secure
```

!!! note "Globális logout (mind az eszközről)"
    A jelenlegi logout csak a kliens saját refresh tokenjét revokálja. Az "összes eszközről kilépés" funkció a `refresh_tokens` táblán `UPDATE … WHERE user_id = X` formában futtatható (lásd [refresh-tokens.md](refresh-tokens.md)). Endpointot M5-ben kap.

## `GET /api/v1/auth/me`

A bejelentkezett felhasználó adatait adja. `#[RequireAuth]` — a Dispatcher 401-et dob a Bearer token hiányában.

**Kérés**:

```http
GET /api/v1/auth/me HTTP/1.1
Authorization: Bearer eyJ0eXAi…
```

**Siker (200)**:

```json
{
  "id": 42,
  "email": "user@example.com",
  "username": "alice",
  "roles": ["editor"]
}
```

A friss DB lookup miatt: ha a usert deaktivált a backend (`is_active = false`), itt már 401-et fog adni — **akkor is**, ha a JWT még érvényes.

**Hibák**:

| Status | Helyzet |
|---|---|
| 401 | Hiányzó vagy érvénytelen Bearer token |
| 404 | A JWT-ben lévő `sub` user már nem létezik a DB-ben |

## SPA flow — összekapcsolva

```javascript
// 1. Login
const loginResp = await fetch('/api/v1/auth/login', {
  method: 'POST',
  credentials: 'include',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email, password }),
});
const { access_token, csrf_token } = await loginResp.json();
// access_token memóriában, csrf cookie-ban + memóriában

// 2. Védett kérés
const meResp = await fetch('/api/v1/auth/me', {
  headers: { 'Authorization': `Bearer ${access_token}` },
});

// 3. Access lejár → refresh
async function refresh() {
  const resp = await fetch('/api/v1/auth/refresh', {
    method: 'POST',
    credentials: 'include',
    headers: { 'X-Csrf-Token': csrf_token },
  });
  if (!resp.ok) throw new Error('refresh failed — please log in');
  const data = await resp.json();
  access_token = data.access_token;
  csrf_token = data.csrf_token;
  return access_token;
}

// 4. Logout
await fetch('/api/v1/auth/logout', {
  method: 'POST',
  credentials: 'include',
});
```

A teljes React példa-implementáció az M6 PR-ben érkezik (`examples/react-spa/`).
