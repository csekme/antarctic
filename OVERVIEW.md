# Antarctic — Framework Overview

Állapot: `0.9.0-alpha` (main, 2026-05-24).
Forrás: a `src/Framework/` modul + a `src/html/index.php` belépési pont + GitNexus index (526 szimbólum, 4 modul: Framework / Models / Controllers / Routing) elemzése alapján.

---

## 1. Belépési pont & bootstrap

- [src/html/index.php](src/html/index.php) → [src/Framework/Bootstrap.php](src/Framework/Bootstrap.php)
- A bootstrap sorrend: `session_start()` → custom Dotenv → `Container` → `StandardRouterImpl` → `Dispatcher::handleRequest(Request::createFromGlobals())`.
- Az error/exception handler globálisan regisztrált; jelenleg csak a HTML-renderelő `exceptionHandlerHtml` van bekötve.

## 2. Core osztályok (`src/Framework/`)

| Osztály | Szerep |
|---|---|
| [Dispatcher.php](src/Framework/Dispatcher.php) | Útválasztás → kontroller példányosítás reflectionnel, CSRF check, `HasRoles` / `RequireLogin` attribútumok kiértékelése, interceptor lánc (before/after), végül `Response::send()`. |
| [Routing/Router.php](src/Framework/Routing/Router.php) + [Routing/StandardRouterImpl.php](src/Framework/Routing/StandardRouterImpl.php) | `#[Path]` attribútum-alapú routing. A `ClassExploder` által visszaadott controller mapping alapján regex tábla épül. Támogatja a `{name}` és `{name:regex}` paramétereket. |
| [ClassExploder.php](src/Framework/ClassExploder.php) | Regex-szel olvas PHP forrásfájlokat a `#[Path("...")]` osztály-szintű attribútum kinyeréséhez. |
| [Container.php](src/Framework/Container.php) | Minimális DI, reflection alapú autowire, **nincs singleton cache** (minden `get()` új instance). |
| [Request.php](src/Framework/Request.php) / [Response.php](src/Framework/Response.php) / [ResponseBuilder.php](src/Framework/ResponseBuilder.php) | Saját HTTP réteg (nem PSR-7). Request beolvassa a JSON body-t, ha `Content-Type: application/json`. |
| [AbstractController.php](src/Framework/AbstractController.php) + [Controller.php](src/Framework/Controller.php) | Kontroller ősök. `Controller::__call` magic-dispatch with before/after filter. |
| [Routing/RestController.php](src/Framework/Routing/RestController.php) | Üres váz. |
| [Auth.php](src/Framework/Auth.php), [Token.php](src/Framework/Token.php), [RememberedLogin.php](src/Framework/RememberedLogin.php), [TwoFactor.php](src/Framework/TwoFactor.php) | Session-alapú auth, HMAC token (`application.secretKey`), remember-me cookie, TOTP 2FA (`robthree/twofactorauth`). |
| [Dal.php](src/Framework/Dal.php) | „Active record-szerű" PDO ős, **static connection cache**, `AllowDynamicProperties`. |
| [View.php](src/Framework/View.php) | Twig wrapper. Globálisak: `session`, `flash_messages`, `csrf_token`, `current_user`. `CsrfExtension` injektálja a hidden mezőt minden formba. |
| [ErrorHandler.php](src/Framework/ErrorHandler.php) | Két handler (HTML és JSON), csak a HTML van bekötve a Bootstrap-ben. |
| [InterceptorInterface.php](src/Framework/InterceptorInterface.php) | `Application\` namespace alól configból olvasva, before/after fázis. |
| [HasRoles.php](src/Framework/HasRoles.php) + [RequireLogin.php](src/Framework/RequireLogin.php) | PHP 8 attribútumok kontrollereken / metódusokon. |

## 3. Beépített kontrollerek

Opcionálisan kapcsolható (`framework.useCoreControllers: true` a config JSON-ban):

- `Login`, `Logout`, `Signup` — alap auth flow
- `TwoFactor` — TOTP beállítás + ellenőrzés
- `Profile`, `User` — profil + user list
- `FrameworkDashboard` — admin nézet

Twig nézetek `src/Framework/Views/` alatt, Bootstrap-szerű layout (`base.twig`), `Errors/{401,403,404,500}.twig`.

## 4. Modulok cohezíció (GitNexus)

| Modul | Szimbólumok | Cohezíció |
|---|---|---|
| Framework | 96 | 75% |
| Models | 40 | 63% |
| Controllers | 19 | 82% |
| Routing | 9 | 100% |

## 5. Erősségek

- Tiszta MVC szétválasztás, modern PHP 8.2 (readonly, attribútumok, union/intersection típusok).
- Attribútum-vezérelt routing + role / login gating.
- CSRF védelem alapból (form és JSON header).
- Interceptor lánc + 2FA + remember-me kész.
- Docker fejlesztői környezet (Apache + PostgreSQL/MariaDB/MySQL) + Xdebug.

## 6. Gyengeségek / SPA-blokkolók

1. **Nincs CORS, nincs OPTIONS preflight kezelés.** Külön origin-ű SPA (pl. Vite dev, Vercel-host) nem tud beszélni vele.
2. **CSRF cookie nélküli, session-kötött, és minden GET-en regenerál tokent** ([Dispatcher.php:157-160](src/Framework/Dispatcher.php#L157-L160)) — párhuzamos AJAX kérések egymás tokenjeit írják felül.
3. **Csak session-alapú auth.** Nincs bearer/JWT token kiállítás, nincs `Authorization` header parsing.
4. **A `Response` body csak `string`** — nincs streaming, nincs szigorú JSON/HTML elválasztás a pipeline-ban.
5. **`Container` nem cache-eli az instance-okat**, minden `get()` újat csinál — singleton service-eket nem tud kiszolgálni ([Container.php:21](src/Framework/Container.php#L21)).
6. **`ClassExploder` regex-szel olvas PHP forrást** — törékeny (megjegyzések, több `#[Path]` osztályon, namespace-ütközés), és minden requestnél újrafut.
7. **Reflection minden requesten** — nincs route cache / kompilált tábla.
8. **`AbstractController::redirect()` `exit`-tel** ([AbstractController.php:50](src/Framework/AbstractController.php#L50)) — megszakítja az after-interceptor láncot.
9. **`Dispatcher` `$this->params['method']`-et használ 405-höz, de az nem létezik** ([Dispatcher.php:93](src/Framework/Dispatcher.php#L93)) — soha nem üt 405-öt; a method-check route szinten sem érvényesül.
10. **Statikus `Dal::connection()` PDO** — tesztelhetetlen, nem mockolható, nincs migráció.
11. **Custom `Dotenv`** + plain JSON config + plaintext `secretKey` a configban — env-be valók.
12. **Nincsenek tesztek**, nincs CI, nincs static analysis.
13. **Cookie security flags hiányoznak** (`HttpOnly`, `Secure`, `SameSite`) — [Auth.php:25](src/Framework/Auth.php#L25).
14. **Hibakezelés HTML-t renderel JSON kéréshez is** — nincs content negotiation.

## 7. `feature_auto_routing` branch — döntés

- Tartalom: 1 commit (`ea420d9`, 2024-09-07), egyetlen új fájl: `src/Framework/Routing/AutoRouterImpl.php` (431 sor).
- Mit hoz: `RestController` vs. `MvcController` megkülönböztetés (utóbbi nem létezik), `cors()` metódus, `fix_post()` JSON body, saját `dispatch()` / `match()`.
- Mit nem hoz: olyat, ami a maine ne lenne meg jobban — a maine azóta lekörözte (`Dispatcher` / `StandardRouterImpl` szétválasztás, CSRF, interceptorok, `Container`, attribútumok).
- **Döntés: NEM mergelendő.** A `cors()` ötletet és a `fix_post()` JSON body kezelést a [PLAN.md](PLAN.md) M1 fázisa portolja át tisztán (`CorsMiddleware` + `Request` PUT/PATCH/DELETE JSON body).

## 8. Két azonnal javítható apró bug

- [Dispatcher.php:93](src/Framework/Dispatcher.php#L93) — `$this->params['method']` mindig `null` (a `params` a `Router`-en van, nem a `Dispatcher`-en). 405-ös válasz soha nem fut le.
- [Controller.php:35](src/Framework/Controller.php#L35) — a `__call` magic-dispatch a `$this->response`-t nem írja vissza a callerhez; a return value figyelmen kívül marad.

Mindkettő külön patch-PR-be való (0.9.0-alpha.5), M0 előtt.
