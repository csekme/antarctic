# Első lépések

Ebben a fejezetben felállítunk egy minimális Antarctic alkalmazást, és hozzáadunk egy egyszerű JSON végpontot.

## Előfeltételek

- **PHP 8.2+** (a `composer.json` minimum)
- **Composer**
- **Docker + Docker Compose** (a beépített dev környezethez)
- **PostgreSQL** vagy **MariaDB** kliens (csak ha adatbázisra építesz)

## A repo szerkezete

```
antarctic/
├── docker/              # Apache + DB Dockerfile-ok
├── docs/                # Ez a doksi
├── src/
│   ├── Application/     # ⚠️ A TE alkalmazásod (gitignored)
│   ├── Framework/       # Maga a keretrendszer
│   ├── config/          # Konfigurációs PHP fájlok (CORS stb.)
│   ├── html/            # Webroot (index.php + .htaccess)
│   ├── tests/           # Framework tesztek
│   ├── composer.json
│   ├── phpstan.neon.dist
│   └── phpunit.xml.dist
├── docker-compose.yml
└── mkdocs.yml           # Ez a guide konfigja
```

A te alkalmazás-kódod a `src/Application/` alatt fog élni (kontrollerek, view-k, modellek, DTO-k). Ez a mappa gitignored — a saját git repódba dolgozol benne.

## Indítás

```bash
# 1. Repo klónozása
git clone https://github.com/csekme/antarctic.git
cd antarctic

# 2. Composer install (a src/ a Composer projektgyökér)
cd src
composer install

# 3. Konfigurációs fájl
cp Application/application.json.example Application/application.json
# Szerkeszd ki a DB credentials-eket, app név, stb.

# 4. .env létrehozása
cp .env.example .env

# 5. Docker környezet
cd ..
docker compose up -d
```

A backend a `http://localhost/` címen érhető el.

## Az első végpont

Hozz létre egy `src/Application/Controllers/HelloController.php` fájlt:

```php
<?php
declare(strict_types=1);

namespace Application\Controllers;

use Framework\AbstractController;
use Framework\Path;
use Framework\Response;

class HelloController extends AbstractController
{
    #[Path(path: '/api/v1/hello', method: 'GET')]
    public function index(): Response
    {
        return Response::json([
            'message' => 'Hello from Antarctic',
            'time' => date(DATE_ATOM),
        ]);
    }
}
```

Próbáld ki:

```bash
curl http://localhost/api/v1/hello
# → {"message":"Hello from Antarctic","time":"2026-05-25T08:00:00+02:00"}
```

## Mi történt a háttérben?

1. Az `index.php` betöltötte a `Bootstrap.php`-t.
2. A `Bootstrap` épített egy PSR-15 middleware pipeline-t: `ErrorHandler → Cors → LegacyDispatcher`.
3. A `ClassExploder` scannelte az `Application/` és `Framework/` namespace-ek `#[Path]` attribútumait, és felépítette a routing táblát.
4. A `LegacyDispatcherMiddleware` átalakította a PSR-7 requestet legacy `Request`-té, lefuttatta a kontrollert, és a Response-t visszaalakította PSR-7-re.
5. A `SapiEmitter` kiküldte a választ a kliensnek.

A részletekért olvasd el az [Architektúra](architecture.md) és [HTTP / Middleware](http/middleware.md) fejezeteket.

## Következő lépések

- [Architektúra](architecture.md) — a kérés-feldolgozás teljes útja
- [HTTP / Routing](http/routing.md) — `#[Path]` attribútum, URL paraméterek, HTTP method
- [Konfiguráció](configuration.md) — env változók, JSON config
- [Tesztelés](testing.md) — PHPUnit setup
