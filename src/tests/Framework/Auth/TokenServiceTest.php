<?php

declare(strict_types=1);

namespace Tests\Framework\Auth;

use DateTimeImmutable;
use DomainException;
use Framework\Auth\JwtConfigFactory;
use Framework\Auth\RefreshTokenRepository;
use Framework\Auth\TokenService;
use Lcobucci\JWT\Token\Plain;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class TokenServiceTest extends TestCase
{
    private PDO $pdo;

    /** @var FrozenClock */
    private FrozenClock $clock;

    private TokenService $service;

    protected function setUp(): void
    {
        $this->pdo = self::sqliteWithSchema();
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-01-01T12:00:00+00:00'));

        $keys = self::generateKeypair();
        $config = JwtConfigFactory::create([
            'algorithm' => 'RS256',
            'private_key' => $keys['private'],
            'public_key' => $keys['public'],
        ]);

        $this->service = new TokenService(
            jwt: $config,
            refreshTokens: new RefreshTokenRepository($this->pdo),
            clock: $this->clock,
            issuer: 'antarctic',
            audience: 'antarctic-spa',
            accessTtl: 900,
            refreshTtl: 3600,
            clockSkew: 5,
        );
    }

    public function testIssueAndVerifyAccessToken(): void
    {
        $jwt = $this->service->issueAccessToken(userId: 42, roles: ['admin', 'editor']);

        $token = $this->service->verifyAccess($jwt);
        $this->assertInstanceOf(Plain::class, $token);
        $this->assertSame('42', $token->claims()->get('sub'));
        $this->assertSame(['admin', 'editor'], $token->claims()->get('roles'));
        $this->assertSame('antarctic', $token->claims()->get('iss'));
    }

    public function testExpiredAccessTokenRejected(): void
    {
        $jwt = $this->service->issueAccessToken(1);

        // Lépjünk 16 percet előre — az access token (15min TTL) lejárt
        $this->clock->set(new DateTimeImmutable('2026-01-01T12:16:00+00:00'));

        $this->expectException(DomainException::class);
        $this->expectExceptionCode(401);
        $this->service->verifyAccess($jwt);
    }

    public function testMalformedAccessTokenRejected(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(401);
        $this->service->verifyAccess('definitely.not.a.jwt');
    }

    public function testIssueRefreshTokenIsStoredAndRotatable(): void
    {
        $issued = $this->service->issueRefreshToken(userId: 7);

        $this->assertNotEmpty($issued['token']);
        $this->assertNotEmpty($issued['family_id']);

        $rotated = $this->service->rotateRefresh(
            refreshToken: $issued['token'],
            userId: 7,
            roles: [],
        );

        $this->assertNotEmpty($rotated['access_token']);
        $this->assertNotEmpty($rotated['refresh_token']);
        $this->assertNotSame($issued['token'], $rotated['refresh_token']);
    }

    public function testRefreshRotationKeepsFamilyId(): void
    {
        $issued = $this->service->issueRefreshToken(userId: 7);
        $this->service->rotateRefresh($issued['token'], 7, []);

        $rows = $this->pdo->query('SELECT family_id FROM refresh_tokens')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertCount(2, $rows);
        $this->assertSame($rows[0], $rows[1], 'rotated token must share family with its predecessor');
    }

    public function testReuseOfRevokedRefreshRevokesEntireFamily(): void
    {
        $issued = $this->service->issueRefreshToken(userId: 7);
        $rotation = $this->service->rotateRefresh($issued['token'], 7, []);
        // További rotáció — három aktív family-tagunk lesz
        $this->service->rotateRefresh($rotation['refresh_token'], 7, []);

        // A kliens újra megpróbálja az eredeti tokent — ez reuse.
        try {
            $this->service->rotateRefresh($issued['token'], 7, []);
            $this->fail('reused token should throw');
        } catch (DomainException $e) {
            $this->assertSame(401, $e->getCode());
            $this->assertStringContainsString('reuse', $e->getMessage());
        }

        $stillValid = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM refresh_tokens WHERE revoked_at IS NULL'
        )->fetchColumn();
        $this->assertSame(0, $stillValid, 'whole family must be revoked after reuse');
    }

    public function testWrongUserCannotRotate(): void
    {
        $issued = $this->service->issueRefreshToken(userId: 7);

        $this->expectException(DomainException::class);
        $this->service->rotateRefresh($issued['token'], userId: 99, roles: []);
    }

    public function testUnknownRefreshRejected(): void
    {
        $this->expectException(DomainException::class);
        $this->service->rotateRefresh('this-token-was-never-issued', 7, []);
    }

    public function testExpiredRefreshRejected(): void
    {
        $issued = $this->service->issueRefreshToken(userId: 7);

        // 2 órát előrelépünk; a refresh TTL 3600s = 1 óra
        $this->clock->set(new DateTimeImmutable('2026-01-01T14:00:00+00:00'));

        $this->expectException(DomainException::class);
        $this->service->rotateRefresh($issued['token'], 7, []);
    }

    public function testRevokeRefreshIsIdempotent(): void
    {
        $issued = $this->service->issueRefreshToken(userId: 7);
        $this->service->revokeRefresh($issued['token']);
        $this->service->revokeRefresh($issued['token']); // no-op

        $revoked = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM refresh_tokens WHERE revoked_at IS NOT NULL'
        )->fetchColumn();
        $this->assertSame(1, $revoked);
    }

    private static function sqliteWithSchema(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(<<<'SQL'
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
        return $pdo;
    }

    /**
     * @return array{private: string, public: string}
     */
    private static function generateKeypair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($resource === false) {
            throw new \RuntimeException('failed to generate test keypair');
        }
        openssl_pkey_export($resource, $private);
        $details = openssl_pkey_get_details($resource);
        return ['private' => $private, 'public' => $details['key']];
    }
}

/**
 * Determinisztikus ClockInterface tesztekhez.
 */
final class FrozenClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }
}
