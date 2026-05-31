<?php

declare(strict_types=1);

/**
 * doctrine/migrations DB-kapcsolat konfig.
 *
 * Az env változók ugyanazok mint a `Framework\Dal` használja: DATABASE,
 * DATABASE_HOST, DATABASE_PORT, DATABASE_NAME, DATABASE_USER, DATABASE_PASSWORD.
 *
 * Használat:
 *   vendor/bin/doctrine-migrations migrate          # apply all pending
 *   vendor/bin/doctrine-migrations status           # current vs target
 *   vendor/bin/doctrine-migrations diff             # generate migration from schema diff
 */

$envFile = __DIR__ . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $trimmed = ltrim($line);
        if ($trimmed === '' || $trimmed[0] === '#') {
            continue;
        }
        if (!str_contains($trimmed, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $trimmed, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '' || getenv($name) !== false) {
            continue;
        }
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[0] === substr($value, -1)) {
            $value = substr($value, 1, -1);
        }
        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

$driver = match (strtolower((string) (getenv('DATABASE') ?: 'mariadb'))) {
    'postgres', 'postgresql', 'pgsql' => 'pdo_pgsql',
    default => 'pdo_mysql',
};

return [
    'driver' => $driver,
    'host' => getenv('DATABASE_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('DATABASE_PORT') ?: ($driver === 'pdo_pgsql' ? 5432 : 3306)),
    'dbname' => getenv('DATABASE_NAME') ?: 'antarctic',
    'user' => getenv('DATABASE_USER') ?: 'antarctic',
    'password' => getenv('DATABASE_PASSWORD') ?: '',
    'charset' => $driver === 'pdo_mysql' ? 'utf8mb4' : 'utf8',
];
