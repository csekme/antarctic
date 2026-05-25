# Refresh token rotáció

A refresh tokenek hosszú élet-idejű (30 nap) credentialek, amelyekkel a kliens új access tokenhez juthat anélkül, hogy újra be kellene jelentkeznie. Az Antarctic **rotációs** stratégiát használ: minden refresh hívás generál egy újat, és **revokálja a régit**.

## A teljes flow

```
┌─────────┐  POST /api/v1/auth/login
│ Kliens  │  ── { email, password } ─────────────▶ ┌─────────────────────┐
└─────────┘                                          │ TokenService::issue │
     ▲                                               │ accessToken         │
     │                                               │ + issueRefreshToken │
     │  { accessToken, expiresIn }                   └──────────┬──────────┘
     │  + Set-Cookie: __Host-refresh=…               │ INSERT refresh_tokens
     │  HttpOnly Secure SameSite=Strict              │   user_id, family_id (új UUID),
     ◀───────────────────────────────────────────────│   token_hash, rotated_from=NULL,
                                                     │   expires_at = now + 30d
                                                     ▼
                                                  refresh_tokens
                                                  ┌──────────────────────┐
                                                  │ id=1, fam=A, rev=NULL│
                                                  └──────────────────────┘
```

Néhány óra múlva az access token lejár, a kliens hívja a refresh-t:

```
┌─────────┐  POST /api/v1/auth/refresh
│ Kliens  │  Cookie: __Host-refresh=token1  ──────▶ ┌─────────────────────────┐
└─────────┘                                          │ TokenService::rotate    │
                                                     │  1. find hash(token1)   │
                                                     │  2. revoke id=1         │
                                                     │  3. insert new id=2     │
                                                     │     family_id=A         │
                                                     │     rotated_from=1      │
                                                     └────────────┬────────────┘
                                                                  │
     ◀──── Set-Cookie: __Host-refresh=token2 ◀────────────────────┘
           { accessToken (új), expiresIn }

                                                  refresh_tokens
                                                  ┌──────────────────────┐
                                                  │ id=1, fam=A, rev=NOW │  ◀ revokálva
                                                  │ id=2, fam=A, rev=NULL│  ◀ aktív
                                                  └──────────────────────┘
```

## Reuse detection

Ha valaki ellopja a `token1`-et (pl. proxy logból), és próbálja `/refresh`-elni, miközben a legitim kliens már `token2`-vel jár:

```
┌──────────┐  POST /api/v1/auth/refresh
│ Támadó   │  Cookie: __Host-refresh=token1  ──────▶ ┌─────────────────────────┐
└──────────┘                                          │ TokenService::rotate    │
                                                      │  find hash(token1)      │
                                                      │  → revoked_at IS NOT…   │
                                                      │  ! REUSE DETECTED       │
                                                      │  → revokeFamily('A')    │
                                                      └────────────┬────────────┘
                                                                   │
                                              ◀── 401 + family A revokálva ─┘

                                                  refresh_tokens
                                                  ┌──────────────────────┐
                                                  │ id=1, fam=A, rev=… (volt)
                                                  │ id=2, fam=A, rev=NOW │  ◀ családtagok mind
                                                  │ id=N, fam=A, rev=NOW │     revokálva
                                                  └──────────────────────┘
```

A legitim kliens is kapja a 401-et a következő refresh-en, és **újra be kell jelentkeznie**. Ez tudatos kompromisszum: a lopás detektálható, de a felhasználó kényelmetlen kijelentkezést kap. Cserébe a támadó nem tud csendben fennmaradni a rendszerben.

## DB séma

A `refresh_tokens` tábla:

| Oszlop | Típus | Jelentés |
|---|---|---|
| `id` | BIGSERIAL | PK |
| `user_id` | BIGINT | FK → users (logikai hivatkozás, fizikai constraint nélkül) |
| `family_id` | VARCHAR(64) | UUID, közös a teljes rotation-családra |
| `token_hash` | CHAR(64) | `SHA-256(plain token)`, **UNIQUE** |
| `rotated_from` | BIGINT NULL | Az előző refresh `id`-je a láncban (NULL = login) |
| `expires_at` | TIMESTAMP | Eredeti TTL: `created_at + refresh_ttl` |
| `revoked_at` | TIMESTAMP NULL | NULL = aktív; nem-NULL = revokált |
| `user_agent` | TEXT NULL | Audit |
| `ip` | VARCHAR(45) | Audit (IPv4 + IPv6) |
| `created_at` | TIMESTAMP | Sor létrejöttének időpontja |

A séma a `doctrine/migrations` által vezérelt — a sémát létrehozó migráció:

- [`db/migrations/Version20260525_010300_CreateRefreshTokens.php`](https://github.com/csekme/antarctic/blob/main/db/migrations/Version20260525_010300_CreateRefreshTokens.php)

Driver-független `Schema` API-val ír (sqlite, MariaDB, PostgreSQL), futtatás: `vendor/bin/doctrine-migrations migrations:migrate`.

## RefreshTokenRepository

A PDO repository a `Framework\Auth\RefreshTokenRepository`. Műveletek:

| Metódus | Mit csinál |
|---|---|
| `store(...)` | Új refresh token sor. |
| `findByHash($tokenHash)` | Visszaadja a sort vagy `null`-t. |
| `markRotated($id, $at)` | `revoked_at = $at` ahol `id` és `revoked_at IS NULL`. |
| `revokeFamily($familyId)` | Az egész family minden aktív sorát revokálja. Reuse esetén ezt hívja a `TokenService`. |
| `purgeExpired($before)` | Lejárt vagy régen revokált sorok törlése. Cron job-ból futtatandó. |

A repository **stateless**, csak PDO-t kap. Tesztben sqlite in-memory-t adsz neki (lásd `RefreshTokenRepositoryTest`).

## Cookie konvenció

A refresh token a kliensen **`__Host-refresh`** nevű cookie-ban él. A `__Host-` prefix kötelez:

- `Secure` (csak HTTPS).
- `Path=/` (subdomain elérés tiltva).
- **NINCS** `Domain` (csak az aktuális host).

Plusz beállítások:

- `HttpOnly` — JS nem érheti el.
- `SameSite=Strict` — semmilyen cross-site request nem viszi magával.

Ennek egyik következménye: a `__Host-refresh` cookie **csak ugyanazon az originen** él. Ha a SPA külön origin alól megy (separate deploy), két lehetőség:

1. **CORS + `credentials: include`** — a böngésző automatikusan elküldi a refresh cookie-t (de a `SameSite=Strict` ezt nem engedi cross-originra). Kompromisszum: `SameSite=Lax`.
2. **Drop-in deploy** (a SPA azonos origin alól megy) — nincs probléma, marad `Strict`.

A javasolt deploy a **drop-in (embedded)** — részletek a [Deployment](../deployment.md) oldalon az `APP_SPA_MODE` env változóról.

## Karbantartás

### Lejárt tokenek tisztítása

Cron / scheduled job a `RefreshTokenRepository::purgeExpired($before)` hívásával:

```php
$repo->purgeExpired(new DateTimeImmutable('-7 days'));
```

Ajánlás: heti 1-szer, retain 7-30 napos history-t audit-célra.

### "Force logout all sessions"

Egy user összes aktív refresh tokenjét revokálni:

```sql
UPDATE refresh_tokens
   SET revoked_at = NOW()
 WHERE user_id = ?
   AND revoked_at IS NULL;
```

A felhasználó a következő refresh-nél kijelentkezik.

## Tesztpéldák

A `TokenServiceTest` minden kritikus pontot fed:

- `testIssueRefreshTokenIsStoredAndRotatable`
- `testRefreshRotationKeepsFamilyId`
- **`testReuseOfRevokedRefreshRevokesEntireFamily`** ← a security-kritikus
- `testWrongUserCannotRotate`
- `testUnknownRefreshRejected`
- `testExpiredRefreshRejected`
- `testRevokeRefreshIsIdempotent`

Forrás: [`tests/Framework/Auth/TokenServiceTest.php`](https://github.com/csekme/antarctic/blob/main/src/tests/Framework/Auth/TokenServiceTest.php).
