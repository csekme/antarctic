<?php

declare(strict_types=1);

/**
 * doctrine/migrations konfiguráció.
 *
 * A migration osztályok a `Db\Migrations` namespace alatt élnek
 * (`/db/migrations/` mappa, repo-gyökérből). A `--db` paraméterek a
 * `migrations-db.php`-ben vannak.
 */

return [
    'table_storage' => [
        'table_name' => 'doctrine_migration_versions',
        'version_column_name' => 'version',
        'version_column_length' => 191,
        'executed_at_column_name' => 'executed_at',
        'execution_time_column_name' => 'execution_time',
    ],
    'migrations_paths' => [
        'Db\\Migrations' => __DIR__ . '/../db/migrations',
    ],
    'all_or_nothing' => true,
    'check_database_platform' => true,
    'organize_migrations' => 'none',
    'transactional' => true,
];
