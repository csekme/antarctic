<?php

declare(strict_types=1);

namespace Tests\Db;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * Az M4.a-ban szállított 4 doctrine-migration felépíti a teljes alap-sémát
 * egy sqlite in-memory adatbázison. A teszt egyszerre kettőt mér:
 *
 *   1. A migration osztályok betöltődnek (a `migrations_paths` config helyes).
 *   2. A migrate() lefut, és a vártan négy domain-tábla létrejön
 *      (`user`, `role`, `user_role`, `two_factor`, `refresh_tokens`) +
 *      a doctrine_migration_versions meta-tábla.
 */
final class MigrationsTest extends TestCase
{
    private static string $repoRoot;

    public static function setUpBeforeClass(): void
    {
        self::$repoRoot = dirname(__DIR__, 3);

        // Db\Migrations\* PSR-4 mapping a `db/migrations/`-re — composer.json-ban
        // direkt nincs (production autoload-classmap-be ne kerüljön), így itt
        // regisztráljuk a teszt-időre.
        spl_autoload_register(static function (string $class): void {
            if (str_starts_with($class, 'Db\\Migrations\\')) {
                $file = self::$repoRoot . '/db/migrations/' . substr($class, strlen('Db\\Migrations\\')) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
    }

    public function testAllMigrationsAreDiscovered(): void
    {
        $df = $this->factory();
        $migrations = $df->getMigrationRepository()->getMigrations();
        $this->assertGreaterThanOrEqual(4, count($migrations), 'Expected at least 4 migrations');
    }

    public function testMigrationsApplyOnSqlite(): void
    {
        $df = $this->factory();
        $df->getMetadataStorage()->ensureInitialized();

        $aliasResolver = $df->getVersionAliasResolver();
        $target = $aliasResolver->resolveVersionAlias('latest');
        $plan = $df->getMigrationPlanCalculator()->getPlanUntilVersion($target);
        $df->getMigrator()->migrate($plan, new MigratorConfiguration());

        $sm = $df->getConnection()->createSchemaManager();
        $tables = $sm->listTableNames();

        // sqlite a tábla-neveket case-érzékenyen tárolja, ahogy létrehoztuk.
        $this->assertContains('user', $tables);
        $this->assertContains('role', $tables);
        $this->assertContains('user_role', $tables);
        $this->assertContains('two_factor', $tables);
        $this->assertContains('refresh_tokens', $tables);
    }

    public function testRoleSeedRowsInserted(): void
    {
        $df = $this->factory();
        $df->getMetadataStorage()->ensureInitialized();
        $plan = $df->getMigrationPlanCalculator()->getPlanUntilVersion(
            $df->getVersionAliasResolver()->resolveVersionAlias('latest'),
        );
        $df->getMigrator()->migrate($plan, new MigratorConfiguration());

        $names = $df->getConnection()->fetchFirstColumn('SELECT name FROM role ORDER BY name');
        $this->assertSame(['ROLE_ADMIN', 'ROLE_USER'], $names);
    }

    private function factory(): DependencyFactory
    {
        $conn = $this->sqliteConnection();
        return DependencyFactory::fromConnection(
            new PhpFile(self::$repoRoot . '/src/migrations.php'),
            new ExistingConnection($conn),
        );
    }

    private function sqliteConnection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }
}
