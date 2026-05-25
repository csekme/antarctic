# Antarctic — React SPA example

A minimal Vite + React + TypeScript single-page app that consumes the
Antarctic backend. It demonstrates the canonical client side of the
RS256 JWT + refresh-cookie design:

- in-memory access token (never written to `localStorage`)
- `__Host-refresh` HttpOnly cookie minted by the backend, rotated on every
  refresh, and exchanged transparently when the access token expires
- CSRF double-submit (`X-CSRF-Token` header echoing the `csrf_token` cookie)
  on the refresh endpoint
- a `ProtectedRoute` guard that waits for the initial `/me` round-trip
  before redirecting, so a page reload with a valid refresh cookie does not
  flash the login screen

## Pages

| Route      | Auth         | Notes                                  |
|------------|--------------|----------------------------------------|
| `/`        | public       | static content, no API call            |
| `/login`   | public       | email + password, optional 2FA TOTP    |
| `/profile` | required     | fetches `GET /api/v1/auth/me`          |

## Run

```bash
cd examples/react-spa
cp .env.example .env
npm install
npm run dev
```

### Same-origin (recommended for local dev)

Leave `VITE_API_BASE` empty in `.env`. Vite's dev server proxies
`/api/v1/*` to `APP_BACKEND_ORIGIN` (default `http://localhost:8080`),
so the browser sees a single origin — `__Host-` cookies survive and
no CORS config is needed.

### Separate-origin

Set `VITE_API_BASE=http://localhost:8080`. Then update the backend's
`src/config/cors.php` to allow the Vite dev origin
(`http://localhost:5173`) with `credentials: true`. The `__Host-` cookie
prefix still requires `Secure`, so the backend must be reachable over
HTTPS (or you must drop the prefix in dev).

## Project layout

```
src/
├── api/
│   ├── auth.ts          login / logout / me / 2FA verify
│   └── client.ts        fetch wrapper + 401 → /refresh → retry
├── auth/
│   ├── AuthContext.tsx  React context + provider
│   └── ProtectedRoute.tsx
├── components/
│   └── Layout.tsx       header + <Outlet>
├── pages/
│   ├── Public.tsx
│   ├── Login.tsx
│   └── Profile.tsx
├── App.tsx              <Routes> wiring
└── main.tsx             root render
```

## What this example deliberately omits

- a router data layer (TanStack Router / loaders) — kept to plain
  `react-router-dom@6` for familiarity
- a query cache (TanStack Query / SWR) — `/me` is fetched in
  `AuthContext` directly, which is enough for the demo
- styling beyond inline styles
- error boundaries

These are easy to layer on; the auth-and-token shape would not change.
