<?php

declare(strict_types=1);

namespace Tests\Framework\Repositories;

use Framework\Repositories\TwoFactorRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class TwoFactorRepositoryTest extends TestCase
{
    private PDO $pdo;
    private TwoFactorRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE two_factor (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                method TEXT,
                secret_key TEXT,
                passcode TEXT,
                enabled INTEGER DEFAULT 0,
                passcode_expired_at TEXT
            );
        SQL);
        $this->repo = new TwoFactorRepository($this->pdo);
    }

    public function testFindByUserIdAndMethodReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repo->findByUserIdAndMethod(42, 'app'));
    }

    public function testEnrollInsertsNewRow(): void
    {
        $this->repo->enroll(42, 'app', 'SECRET123', enabled: true);

        $row = $this->repo->findByUserIdAndMethod(42, 'app');
        $this->assertIsArray($row);
        $this->assertSame('SECRET123', $row['secret_key']);
        $this->assertSame(1, (int) $row['enabled']);
    }

    public function testEnrollUpdatesExistingRow(): void
    {
        $this->repo->enroll(42, 'app', 'ORIGINAL');
        $this->repo->enroll(42, 'app', 'ROTATED');

        $rows = $this->repo->findByUserId(42);
        $this->assertCount(1, $rows);
        $this->assertSame('ROTATED', $rows[0]['secret_key']);
    }

    public function testSetEnabledFlips(): void
    {
        $this->repo->enroll(42, 'app', 'SECRET', enabled: true);
        $this->repo->setEnabled(42, 'app', false);

        $row = $this->repo->findByUserIdAndMethod(42, 'app');
        $this->assertSame(0, (int) $row['enabled']);
    }

    public function testEnabledMethodsReturnsOnlyActive(): void
    {
        $this->repo->enroll(42, 'app', 'A', enabled: true);
        $this->repo->enroll(42, 'email', 'E', enabled: false);

        $this->assertSame(['app'], $this->repo->enabledMethods(42));
    }

    public function testFindByUserIdReturnsAll(): void
    {
        $this->repo->enroll(42, 'app', 'A');
        $this->repo->enroll(42, 'email', 'E');

        $rows = $this->repo->findByUserId(42);
        $this->assertCount(2, $rows);
    }
}
