<?php

declare(strict_types=1);

/**
 * Rate-limit configuration. The `enabled` flag is the master switch — when
 * false the middleware is skipped entirely at Bootstrap time, so there is
 * zero overhead in environments that don't need it (e.g. tests).
 *
 * `trust_proxy` toggles `X-Forwarded-For` honouring; only turn it on behind
 * a reverse proxy that you control, otherwise clients can spoof their IP.
 *
 * `rules` are evaluated top-to-bottom; the first matching `path_prefix`
 * wins. Order narrower rules before catch-alls.
 *
 * `key`: `"ip"` (default) or `"user"` — when `"user"`, the bucket key is the
 * authenticated user id (from `authUser` request attribute); anonymous
 * requests fall back to IP.
 */
return [
    'enabled' => filter_var(getenv('APP_RATE_LIMIT') ?: '0', FILTER_VALIDATE_BOOL),
    'trust_proxy' => filter_var(getenv('APP_TRUST_PROXY') ?: '0', FILTER_VALIDATE_BOOL),
    'rules' => [
        // Brute-force protection for the password and 2FA flows.
        ['name' => 'auth-login', 'path_prefix' => '/api/v1/auth/login', 'limit' => 5, 'window' => 60],
        ['name' => 'auth-2fa', 'path_prefix' => '/api/v1/auth/2fa/verify', 'limit' => 5, 'window' => 60],
        // Catch-all for the rest of the API. Tune as endpoints grow.
        ['name' => 'api-default', 'path_prefix' => '/api/v1/', 'limit' => 120, 'window' => 60],
    ],
];
