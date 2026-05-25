<?php

declare(strict_types=1);

/**
 * JWT és refresh token konfiguráció.
 *
 * Production-ben a private/public key tartalmát env változón keresztül érdemes
 * adni (JWT_PRIVATE_KEY / JWT_PUBLIC_KEY), különben a `var/keys/` fájlokat
 * használja. A var/keys/ csak development setupban él (gitignore-olva).
 */

$root = dirname(__DIR__);

return [
    'algorithm' => 'RS256',
    'issuer' => getenv('JWT_ISSUER') ?: 'antarctic',
    'audience' => getenv('JWT_AUDIENCE') ?: 'antarctic-spa',
    'access_ttl' => (int) (getenv('JWT_ACCESS_TTL') ?: 900),          // 15 perc
    'refresh_ttl' => (int) (getenv('JWT_REFRESH_TTL') ?: 2592000),    // 30 nap
    'clock_skew' => (int) (getenv('JWT_CLOCK_SKEW') ?: 5),            // másodperc
    'private_key' => getenv('JWT_PRIVATE_KEY') ?: null,
    'public_key' => getenv('JWT_PUBLIC_KEY') ?: null,
    'private_key_path' => getenv('JWT_PRIVATE_KEY_PATH') ?: $root . '/var/keys/jwt-private.pem',
    'public_key_path' => getenv('JWT_PUBLIC_KEY_PATH') ?: $root . '/var/keys/jwt-public.pem',
    'private_key_passphrase' => getenv('JWT_PRIVATE_KEY_PASSPHRASE') ?: '',
];
