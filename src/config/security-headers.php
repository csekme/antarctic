<?php

declare(strict_types=1);

/**
 * Baseline security headers. Tune via env vars when the SPA host or embedded
 * assets need a wider CSP/Permissions-Policy than the defaults:
 *
 *   APP_CSP                 Content-Security-Policy value (default: API-only)
 *   APP_PERMISSIONS_POLICY  Permissions-Policy value
 *   APP_HSTS_MAX_AGE        HSTS max-age seconds (default: 1 year)
 *   APP_HSTS_PRELOAD        "1" to append "; preload" (only set if domain is on the HSTS preload list)
 *   APP_TRUST_PROXY         "1" to honour X-Forwarded-Proto for HSTS gating
 *
 * Default CSP intentionally lacks `frame-ancestors` because we send
 * `X-Frame-Options: DENY` separately for legacy-browser parity. If the SPA is
 * embedded under `src/html/app/`, broaden the CSP via env.
 */
$csp = getenv('APP_CSP');
if ($csp === false || $csp === '') {
    $csp = "default-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'";
}

$permissions = getenv('APP_PERMISSIONS_POLICY');
if ($permissions === false || $permissions === '') {
    $permissions = 'camera=(), microphone=(), geolocation=(), interest-cohort=()';
}

$hstsMaxAge = (int) (getenv('APP_HSTS_MAX_AGE') ?: '31536000');
$hsts = "max-age={$hstsMaxAge}; includeSubDomains";
if (filter_var(getenv('APP_HSTS_PRELOAD') ?: '0', FILTER_VALIDATE_BOOL)) {
    $hsts .= '; preload';
}

return [
    'hsts_only_https' => true,
    'trust_proxy' => filter_var(getenv('APP_TRUST_PROXY') ?: '0', FILTER_VALIDATE_BOOL),
    'headers' => [
        'Strict-Transport-Security' => $hsts,
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => $permissions,
        'Content-Security-Policy' => $csp,
        'X-Permitted-Cross-Domain-Policies' => 'none',
    ],
];
