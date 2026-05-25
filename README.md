# Antarctic

**SPA-native PHP 8.2 backend framework** — JWT-secured, OpenAPI-documented, container-ready.

Antarctic is a lightweight PHP framework optimized for the role of a stateless backend behind a single-page application. It ships with everything you need to run a production HTTP API: PSR-7/15 middleware pipeline, RS256 JWT authentication with refresh token rotation, attribute-based routing, php-di container, doctrine migrations, OpenAPI 3.1 spec generation, validated request DTOs, rate-limiting, security headers, structured JSON logging, and a multi-stage Docker production stack.

## Highlights

- **PSR-7 / PSR-15** HTTP layer with a configurable middleware pipeline.
- **Attribute-based routing** (`#[Path]`) with method-aware 404/405 resolution and an optional production route cache.
- **RS256 JWT** authentication: short-lived access tokens, refresh token rotation with reuse detection, optional 2FA challenge flow, and `#[RequireAuth]` / `#[RequireRole]` policy attributes.
- **PSR-11 container** (`php-di`) with autowiring, attribute-DI, and an opt-in compilation cache.
- **Doctrine migrations** with platform-agnostic schema definitions (SQLite, MariaDB, PostgreSQL); PDO-injected repository layer.
- **Request DTOs + symfony/validator** → automatic 422 `application/problem+json` responses with structured `errors`.
- **OpenAPI 3.1 + Swagger UI** powered by `zircote/swagger-php`; `bin/console openapi:dump` for build-time caching.
- **Rate limiting**: PSR-15 middleware with in-memory and Redis stores (both `predis/predis` and ext-redis `phpredis` adapters).
- **Production hardening**: security headers (CSP, HSTS, Referrer-Policy, …), `X-Request-Id` trace propagation, JSON-structured Monolog logging, proxy-aware HTTPS redirect.
- **Drop-in or separate SPA deploy** via the `APP_SPA_MODE` env variable; ships with a working React + Vite example under [`examples/react-spa/`](examples/react-spa/).
- **Multi-stage production Docker image** with PHP-FPM, Nginx, PostgreSQL, and Redis healthchecks.

## Requirements

- PHP **8.2+** (with `pdo`, `pdo_pgsql` or `pdo_mysql`, `openssl`, `json`)
- Composer
- Optional: Docker + Docker Compose for the bundled dev/prod stacks
- Optional: `ext-redis` if you want the `phpredis` rate-limit adapter

## Quick start

```bash
# Clone
git clone https://github.com/csekme/antarctic.git
cd antarctic

# Install dependencies
cd src
composer install
cp .env.example .env                            # edit DATABASE_*, CORS_*, …

# Generate JWT signing keys
bin/console keys:generate                       # writes var/keys/jwt-{private,public}.pem

# Apply migrations
vendor/bin/doctrine-migrations migrations:migrate --no-interaction

# Run tests
vendor/bin/phpunit

# Dev server
php -S localhost:8080 -t html                   # → http://localhost:8080/api/v1/healthz
```

Or with Docker (dev stack):

```bash
docker compose up -d                            # Apache + selectable DB
```

Production stack (PHP-FPM + Nginx + PostgreSQL + Redis):

```bash
DATABASE_PASSWORD=secret docker compose -f docker-compose.prod.yml up -d --build
```

## Documentation

The full developer guide lives under [`docs/developer-guide/`](docs/developer-guide/) and is rendered with MkDocs Material:

```bash
pip install mkdocs-material
mkdocs serve                                    # → http://127.0.0.1:8000
```

Key entry points:

- [Getting started](docs/developer-guide/getting-started.md)
- [Architecture](docs/developer-guide/architecture.md) — middleware pipeline, request flow
- [Authentication](docs/developer-guide/auth/index.md) — JWT, refresh tokens, endpoints, keys
- [Routing](docs/developer-guide/http/routing.md)
- [Validation + DTOs](docs/developer-guide/http/validation.md)
- [OpenAPI + Swagger UI](docs/developer-guide/http/openapi.md)
- [Rate limiting](docs/developer-guide/http/rate-limit.md)
- [Deployment + SPA modes](docs/developer-guide/deployment.md)

## React SPA example

A working React + TypeScript + Vite SPA demonstrating the canonical client-side JWT + refresh cookie + CSRF double-submit flow:

```bash
cd examples/react-spa
npm ci
npm run dev                                     # Vite dev server with /api proxy
```

See [`examples/react-spa/README.md`](examples/react-spa/README.md) for the design rationale and integration details.

## CLI commands

| Command | Purpose |
|---|---|
| `bin/console keys:generate` | Generate the RS256 JWT signing key pair (`var/keys/`). |
| `bin/console route:cache` | Pre-compile the routing table to `var/cache/routes.php` (production). |
| `bin/console openapi:dump` | Generate `var/cache/openapi.json` for `/api/v1/docs.json`. |

## License

Antarctic is released under the [GNU General Public License v3](LICENSE).

## Contact

Krisztián Csekme — [krisztian.csekme@visma.com](mailto:krisztian.csekme@visma.com).
