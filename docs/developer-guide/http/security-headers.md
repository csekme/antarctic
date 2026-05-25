# Security headers

A `Framework\Http\SecurityHeadersMiddleware` baseline biztonsági header-ekkel dekorálja minden választ — error response-okat és CORS preflight-okat is. Az M5 óta a Bootstrap pipeline legkülső eleme.

## Default header-ek

| Header | Érték | Cél |
|---|---|---|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | HSTS, csak HTTPS-en (gating: lásd lent) |
| `X-Content-Type-Options` | `nosniff` | MIME-sniffing letiltás |
| `X-Frame-Options` | `DENY` | Clickjacking elleni védelem (legacy böngészők) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Cross-origin referrer szivárgás |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), interest-cohort=()` | Sensors / FLoC opt-out |
| `Content-Security-Policy` | `default-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | XSS mitigation |
| `X-Permitted-Cross-Domain-Policies` | `none` | Adobe Flash/PDF policy file-ok blokkolása |

## HSTS gating

A `Strict-Transport-Security` header **csak HTTPS-en megy ki**, hogy ne pinneld a klienst egy olyan sémához, amit nem tudsz kiszolgálni. A "HTTPS-e a request" döntés a `Framework\Http\RequestScheme::isHttps()` helperben dől el:

1. ha `$uri->getScheme() === 'https'`, igen.
2. ha `APP_TRUST_PROXY=1` és `X-Forwarded-Proto: https`, igen.
3. egyébként nem.

A `APP_TRUST_PROXY=1`-et csak akkor kapcsold be, ha a backend egy te általad kontrollált reverse proxy mögött fut — különben a kliens spoofolhatja a header-t.

## Env overrides

A [config/security-headers.php](https://github.com/csekme/antarctic/blob/main/src/config/security-headers.php) defaultokat ad, env változókkal felülírható:

| Env | Default | Hatás |
|---|---|---|
| `APP_CSP` | API-only CSP | Tetszőleges CSP directive — SPA host-spec |
| `APP_PERMISSIONS_POLICY` | sensors opt-out | Permissions-Policy érték |
| `APP_HSTS_MAX_AGE` | `31536000` | HSTS max-age másodpercben |
| `APP_HSTS_PRELOAD` | `0` | `1` → `; preload` toldás (csak ha a domain a preload listán van) |
| `APP_TRUST_PROXY` | `0` | `X-Forwarded-Proto` honorálás |

## SPA host CSP

Embedded SPA-nál (`APP_SPA_MODE=embedded`) a SPA build inline-style és inline-script darabokat tartalmazhat. Bővítsd a CSP-t env-ből:

```bash
APP_CSP="default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'"
```

## Felülírás endpoint-szinten

A middleware **nem** írja felül a downstream által már beállított header-eket — egy controller egyedi `X-Frame-Options: SAMEORIGIN` válasza érintetlen marad. Ez a pattern az iframe-elt admin felületekhez hasznos.

## Verifikáció

```bash
curl -I https://api.example.com/api/v1/me

HTTP/1.1 200 OK
strict-transport-security: max-age=31536000; includeSubDomains
x-content-type-options: nosniff
x-frame-options: DENY
referrer-policy: strict-origin-when-cross-origin
content-security-policy: default-src 'self'; ...
```
