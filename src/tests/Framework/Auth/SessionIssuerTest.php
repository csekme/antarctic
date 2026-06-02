<?php

declare(strict_types=1);

namespace Tests\Framework\Auth;

use DateTimeImmutable;
use Framework\Auth\JwtConfigFactory;
use Framework\Auth\RefreshTokenRepository;
use Framework\Auth\SessionIssuer;
use Framework\Auth\TokenService;
use Framework\Dal;
use Framework\Models\User;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class SessionIssuerTest extends TestCase
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
                password_hash TEXT, activation_hash TEXT,
                is_active INTEGER DEFAULT 1,
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

        $keys = self::generateKeypair();
        $config = JwtConfigFactory::create([
            'algorithm' => 'RS256',
            'private_key' => $keys['private'],
            'public_key' => $keys['public'],
        ]);
        $clock = new class implements ClockInterface {
            public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-01-01T12:00:00+00:00'); }
        };
        $this->tokenService = new TokenService(
            jwt: $config,
            refreshTokens: new RefreshTokenRepository($this->pdo),
            clock: $clock,
            issuer: 'antarctic',
            audience: 'antarctic-spa',
            accessTtl: 900,
            refreshTtl: 3600,
            clockSkew: 5,
        );
    }

    protected function tearDown(): void
    {
        Dal::setConnection(null);
    }

    public function testIssuePersistsRefreshAndReturnsSessionStruct(): void
    {
        $user = new User();
        $user->id = 1;
        $user->email = 'alice@example.com';
        $issuer = new SessionIssuer($this->tokenService, accessTtl: 900, refreshTtl: 3600);

        $session = $issuer->issue($user, userAgent: 'curl/8', ip: '127.0.0.1');

        $this->assertNotSame('', $session->accessToken);
        $this->assertNotSame('', $session->refreshToken);
        $this->assertSame(900, $session->accessTtl);
        $this->assertSame(3600, $session->refreshTtl);

        $row = $this->pdo->query('SELECT user_id, user_agent, ip FROM refresh_tokens')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $row['user_id']);
        $this->assertSame('curl/8', $row['user_agent']);
        $this->assertSame('127.0.0.1', $row['ip']);
    }

    /**
     * @return array{private: string, public: string}
     */
    private static function generateKeypair(): array
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
