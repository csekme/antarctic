<?php

declare(strict_types=1);

namespace Tests\Framework\Auth;

use DateTimeImmutable;
use Framework\Auth\JwtConfigFactory;
use Framework\Auth\RefreshSessionService;
use Framework\Auth\RefreshSessionStatus;
use Framework\Auth\RefreshTokenRepository;
use Framework\Auth\TokenService;
use Framework\Dal;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class RefreshSessionServiceTest extends TestCase
{
    private PDO $pdo;
    private TokenService $tokenService;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE user (
                id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT,
                username TEXT, firstname TEXT, lastname TEXT, email TEXT,
                password_hash TEXT, activation_hash TEXT, is_active INTEGER DEFAULT 1,
                password_reset_hash TEXT, password_reset_expires_at TEXT,
                created_at TEXT, updated_at TEXT
            );
            CREATE TABLE user_role (user_id INTEGER NOT NULL, role_id INTEGER NOT NULL);
            CREATE TABLE role (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT);
            CREATE TABLE refresh_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
                family_id TEXT NOT NULL, token_hash TEXT NOT NULL UNIQUE,
                rotated_from INTEGER NULL, expires_at TEXT NOT NULL,
                revoked_at TEXT NULL, user_agent TEXT NULL, ip TEXT NULL,
                created_at TEXT NOT NULL
            );
        SQL);
        Dal::setConnection($this->pdo);
        $this->pdo->prepare('INSERT INTO user (id, uuid, email, username, is_active) VALUES (1, ?, ?, ?, 1)')
            ->execute(['u-1', 'alice@example.com', 'alice']);

        $keys = $this->generateKeypair();
        $config = JwtConfigFactory::create([
            'algorithm' => 'RS256',
            'private_key' => $keys['private'],
            'public_key' => $keys['public'],
        ]);
        $clock = new class implements ClockInterface {
            public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-01-01T12:00:00+00:00'); }
        };
        $this->tokenService = new TokenService(
            jwt: $config, refreshTokens: new RefreshTokenRepository($this->pdo),
            clock: $clock, issuer: 'antarctic', audience: 'antarctic-spa',
            accessTtl: 900, refreshTtl: 3600, clockSkew: 5,
        );
    }

    protected function tearDown(): void
    {
        Dal::setConnection(null);
    }

    public function testMissingCookieReturnsMissingCookie(): void
    {
        $service = new RefreshSessionService($this->tokenService, 900);
        $result = $service->rotate(null, 'csrf', 'csrf', null, null);

        $this->assertSame(RefreshSessionStatus::MissingCookie, $result->status);
    }

    public function testCsrfMismatchReturnsCsrfMismatch(): void
    {
        $service = new RefreshSessionService($this->tokenService, 900);
        $result = $service->rotate('rt', 'cookie-csrf', 'header-csrf', null, null);

        $this->assertSame(RefreshSessionStatus::CsrfMismatch, $result->status);
    }

    public function testUnknownTokenReturnsTokenUnknown(): void
    {
        $service = new RefreshSessionService($this->tokenService, 900);
        $result = $service->rotate('unknown-token', 'c', 'c', null, null);

        $this->assertSame(RefreshSessionStatus::TokenUnknown, $result->status);
    }

    public function testSuccessfulRotationReturnsNewAccessAndRefresh(): void
    {
        $issued = $this->tokenService->issueRefreshToken(1, null, null);
        $service = new RefreshSessionService($this->tokenService, 900);

        $result = $service->rotate($issued['token'], 'c', 'c', null, null);

        $this->assertSame(RefreshSessionStatus::Ok, $result->status);
        $this->assertNotNull($result->accessToken);
        $this->assertNotNull($result->refreshToken);
        $this->assertNotSame($issued['token'], $result->refreshToken);
    }

    public function testReusedTokenRevokesFamilyAndFails(): void
    {
        $issued = $this->tokenService->issueRefreshToken(1, null, null);
        $service = new RefreshSessionService($this->tokenService, 900);

        // First rotation succeeds, returning a fresh token.
        $first = $service->rotate($issued['token'], 'c', 'c', null, null);
        $this->assertSame(RefreshSessionStatus::Ok, $first->status);

        // Replaying the ORIGINAL token (the one we already rotated away from)
        // must trigger reuse-detection in TokenService::rotateRefresh — the
        // entire family is revoked, and this attempt fails.
        $second = $service->rotate($issued['token'], 'c', 'c', null, null);
        $this->assertSame(RefreshSessionStatus::RotationFailed, $second->status);
    }

    /**
     * @return array{private: string, public: string}
     */
    private function generateKeypair(): array
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($resource === false) {
            throw new \RuntimeException('failed to generate test keypair');
        }
        openssl_pkey_export($resource, $private);
        $details = openssl_pkey_get_details($resource);
        return ['private' => $private, 'public' => $details['key']];
    }
}
