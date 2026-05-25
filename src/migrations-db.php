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
