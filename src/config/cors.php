<?php

declare(strict_types=1);

/**
 * CORS allow-list. Overridable via env vars (CORS_ALLOWED_ORIGINS as a
 * comma-separated list). Drop-in SPA deploys typically need no entries here
 * because the SPA is served from the same origin as the API.
 */
$envOrigins = getenv('CORS_ALLOWED_ORIGINS');
$origins = $envOrigins === false || $envOrigins === ''
    ? []
    : array_values(array_filter(array_map('trim', explode(',', $envOrigins))));

return [
    'allowed_origins' => $origins,
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Authorization', 'Content-Type', 'X-Csrf-Token', 'X-Requested-With'],
    'exposed_headers' => ['X-Request-Id'],
    'allow_credentials' => true,
    'max_age' => 600,
];
