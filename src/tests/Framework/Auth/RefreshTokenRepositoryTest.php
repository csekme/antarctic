<?php

declare(strict_types=1);

namespace Tests\Framework\Auth;

use DateTimeImmutable;
use Framework\Auth\RefreshTokenRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class RefreshTokenRepositoryTest extends TestCase
{
    private PDO $pdo;
    private RefreshTokenRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(<<<'SQL'
CREATE TABLE refresh_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    family_id TEXT NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    rotated_from INTEGER NULL,
    expires_at TEXT NOT NULL,
    revoked_at TEXT NULL,
    user_agent TEXT NULL,
    ip TEXT NULL,
    created_at TEXT NOT NULL
);
SQL);
        $this->repo = new RefreshTokenRepository($this->pdo);
    }

    public function testStoreAndFindByHash(): void
    {
        $id = $this->repo->store(
            userId: 1,
            familyId: 'fam-1',
            tokenHash: 'abc123',
            rotatedFrom: null,
            expiresAt: new DateTimeImmutable('+1 day'),
            userAgent: 'Mozilla/5.0',
            ip: '127.0.0.1',
        );

        $this->assertGreaterThan(0, $id);

        $row = $this->repo->findByHash('abc123');
        $this->assertNotNull($row);
        $this->assertSame('fam-1', $row['family_id']);
        $this->assertSame('Mozilla/5.0', $row['user_agent']);
        $this->assertNull($row['revoked_at']);
    }

    public function testFindByHashReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findByHash('does-not-exist'));
    }

    public function testMarkRotatedSetsRevokedAt(): void
    {
        $id = $this->repo->store(1, 'fam', 'h', null, new DateTimeImmutable('+1 day'), null, null);
        $this->repo->markRotated($id, new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

        $row = $this->repo->findByHash('h');
        $this->assertNotNull($row['revoked_at']);
    }

    public function testMarkRotatedIsIdempotent(): void
    {
        $id = $this->repo->store(1, 'fam', 'h', null, new DateTimeImmutable('+1 day'), null, null);
        $this->repo->markRotated($id, new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $this->repo->markRotated($id, new DateTimeImmutable('2027-01-01T00:00:00+00:00'));

        $row = $this->repo->findByHash('h');
        $this->assertStringStartsWith('2026', (string) $row['revoked_at']);
    }

    public function testRevokeFamilyAffectsOnlyActiveRows(): void
    {
        $this->repo->store(1, 'fam-A', 'h1', null, new DateTimeImmutable('+1 day'), null, null);
        $this->repo->store(1, 'fam-A', 'h2', null, new DateTimeImmutable('+1 day'), null, null);
        $idAlreadyRevoked = $this->repo->store(1, 'fam-A', 'h3', null, new DateTimeImmutable('+1 day'), null, null);
        $this->repo->markRotated($idAlreadyRevoked, new DateTimeImmutable('2025-12-31T00:00:00+00:00'));
        $this->repo->store(1, 'fam-B', 'h4', null, new DateTimeImmutable('+1 day'), null, null);

        $affected = $this->repo->revokeFamily('fam-A');

        $this->assertSame(2, $affected);
        $this->assertNull($this->repo->findByHash('h4')['revoked_at']);
    }

    public function testPurgeExpiredRemovesOldRows(): void
    {
        $this->repo->store(1, 'f', 'expired', null, new DateTimeImmutable('2020-01-01T00:00:00+00:00'), null, null);
        $this->repo->store(1, 'f', 'valid', null, new DateTimeImmutable('+1 year'), null, null);

        $deleted = $this->repo->purgeExpired(new DateTimeImmutable('2025-01-01T00:00:00+00:00'));

        $this->assertSame(1, $deleted);
        $this->assertNotNull($this->repo->findByHash('valid'));
        $this->assertNull($this->repo->findByHash('expired'));
    }
}
