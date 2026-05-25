# Autentikáció

Az Antarctic **RS256 JWT** alapú stateless autentikációt használ, **refresh token rotációval** és **reuse detection**-nel. Az auth réteg minden építőeleme — `TokenService`, `RefreshTokenRepository`, `AuthMiddleware`, `#[RequireAuth]`, `#[RequireRole]`, a négy auth endpoint (`/login`, `/refresh`, `/logout`, `/me`) és a 2FA challenge-flow — production-ready.

## A séma röviden

```
┌──────────┐  email+jelszó  ┌──────────────┐    access + refresh    ┌──────────┐
│  Kliens  │ ─────────────▶ │   Backend    │ ─────────────────────▶ │  Kliens  │
│          │                │ /api/v1/auth │  ↳ access: memóriában  │          │
│          │                │   /login     │  ↳ refresh: httpOnly   │          │
└──────────┘                └──────────────┘    cookie              └──────────┘
                                                                            │
                                                  Bearer <access>           │
                            ◀──────────────────────────────────────────────┘
                            (API kérések)

Access token lejár (15 perc):
┌──────────┐  refresh cookie  ┌──────────────┐ új access + új refresh ┌──────────┐
│  Kliens  │ ───────────────▶ │ /auth/refresh│ ─────────────────────▶ │  Kliens  │
└──────────┘                  └──────────────┘                        └──────────┘
                              régi refresh    ────▶  revokálva
```

## Két tokent használunk

| | Access token | Refresh token |
|---|---|---|
| **Forma** | JWT (RS256, lcobucci/jwt) | Random 256-bit string |
| **Hossz** | ~700 char | 64 char (base64-url) |
| **Élet** | 15 perc | 30 nap |
| **Tárolás kliensen** | Memória (JS változó) | httpOnly Secure SameSite=Strict cookie |
| **Tárolás szerveren** | Nincs (stateless) | DB-ben `SHA-256` hash |
| **Mit tartalmaz** | `sub` (userId), `roles`, `iat`, `exp`, `iss`, `aud`, `jti` | Csak opaque szám |
| **Hogy küldjük** | `Authorization: Bearer <token>` | Automatikus cookie |

### Miért nem localStorage?

A localStorage tartalma **bármilyen JS** számára olvasható az adott originen. XSS = teljes token lopás. Az access token memóriában (egy `useState` / context state) él, a refresh token httpOnly cookie-ban, ami JS-ből **nem érhető el**. XSS esetén a támadó nem tudja a refresh-t ellopni, és az access lejár 15 percen belül.

### Miért nem session?

Az SPA elvárása: **stateless backend**. A session-cookie megoldás (régi Antarctic-szerű) konkrétan akadályozza a horizontális skálázást és a CORS-os deploy-t.

## Reuse detection

Ez a leg-fontosabb biztonsági réteg: ha **valaki ellop egy refresh tokent**, és a legitim kliens még él, mindketten próbálkozni fognak rotálni vele. Az első sikerül; a második rögtön azt látja, hogy egy `revoked_at IS NOT NULL` tokenhez próbál hozzáférni. Ekkor a teljes **family** (mind az addig kiállított rotation-lánc) revokálódik, és mindkét felet kikényszerítjük újra-bejelentkezésre.

A `family_id` egy UUID, ami a legelső `issueRefreshToken()` hívásnál keletkezik, és minden rotált utódba átkerül. Részletek: [Refresh token rotáció](refresh-tokens.md).

## Komponensek

| Osztály | Felelősség |
|---|---|
| [`TokenService`](jwt.md) | Access kiállítás + verifikáció, refresh kiállítás + rotáció + revoke. |
| [`RefreshTokenRepository`](refresh-tokens.md) | PDO CRUD a `refresh_tokens` táblán. |
| `JwtConfigFactory` | `Lcobucci\JWT\Configuration` builder, RS256-tal. |
| `SystemClock` | PSR-20 óra production-höz. (Tesztekben `FrozenClock`.) |
| `AuthMiddleware` | PSR-15: Bearer parsing, `AuthenticatedUser` attribute set. Sosem reject-el. |
| [`AuthController`](endpoints.md) | `/api/v1/auth/{login,refresh,logout,me}` endpointok. |
| [`#[RequireAuth]` / `#[RequireRole]`](attributes.md) | Deklaratív policy attribútumok kontroller method-okon. |
| [`KeysGenerateCommand`](keys.md) | `bin/console keys:generate` — RSA kulcspár generátor. |

## Tovább

- [Auth endpointok](endpoints.md) — `/login`, `/refresh`, `/logout`, `/me` + curl és SPA példák
- [`#[RequireAuth]` és `#[RequireRole]`](attributes.md) — deklaratív policy attribútumok
- [JWT és TokenService használata](jwt.md) — issue, verify, claims, példák
- [Refresh token rotáció](refresh-tokens.md) — flow, reuse detection, DB séma
- [Kulcsok kezelése](keys.md) — `keys:generate`, fájl jogosultságok, env-be töltés
