<?php

declare(strict_types=1);

namespace Tests\Framework\Auth;

use DateTimeImmutable;
use Framework\Auth\JwtConfigFactory;
use Framework\Auth\TwoFactorChallengeService;
use Framework\Auth\TwoFactorLoginService;
use Framework\Auth\TwoFactorLoginStatus;
use Framework\Dal;
use Framework\Repositories\TwoFactorRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class TwoFactorLoginServiceTest extends TestCase
{
    private PDO $pdo;
    private TwoFactorChallengeService $challengeService;

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
            CREATE TABLE two_factor (
                id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
                method TEXT NOT NULL, secret_key TEXT, passcode TEXT,
                enabled INTEGER DEFAULT 0, passcode_expired_at TEXT
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
        $this->challengeService = new TwoFactorChallengeService(
            jwt: $config, clock: $clock,
            issuer: 'antarctic', audience: 'antarctic-spa',
            ttl: 300, clockSkew: 5,
        );
    }

    protected function tearDown(): void
    {
        Dal::setConnection(null);
    }

    public function testValidChallengeAndCodeReturnsOk(): void
    {
        (new TwoFactorRepository($this->pdo))->enroll(1, 'app', 'SECRET', enabled: true);
        $challenge = $this->challengeService->issueChallenge(1);
        $service = new TwoFactorLoginService(
            $this->challengeService,
            new TwoFactorRepository($this->pdo),
            static fn (string $s, string $c): bool => $c === '123456',
        );

        $result = $service->verify($challenge, '123456');

        $this->assertSame(TwoFactorLoginStatus::Ok, $result->status);
        $this->assertNotNull($result->user);
    }

    public function testInvalidChallengeJwt(): void
    {
        $service = new TwoFactorLoginService(
            $this->challengeService, new TwoFactorRepository($this->pdo),
            static fn (): bool => true,
        );

        $result = $service->verify('not-a-jwt', '123456');

        $this->assertSame(TwoFactorLoginStatus::ChallengeInvalid, $result->status);
    }

    public function testTwoFactorNotEnabled(): void
    {
        $challenge = $this->challengeService->issueChallenge(1);
        $service = new TwoFactorLoginService(
            $this->challengeService, new TwoFactorRepository($this->pdo),
            static fn (): bool => true,
        );

        $result = $service->verify($challenge, '123456');

        $this->assertSame(TwoFactorLoginStatus::NotEnabled, $result->status);
    }

    public function testInvalidCode(): void
    {
        (new TwoFactorRepository($this->pdo))->enroll(1, 'app', 'SECRET', enabled: true);
        $challenge = $this->challengeService->issueChallenge(1);
        $service = new TwoFactorLoginService(
            $this->challengeService, new TwoFactorRepository($this->pdo),
            static fn (): bool => false,
        );

        $result = $service->verify($challenge, '000000');

        $this->assertSame(TwoFactorLoginStatus::InvalidCode, $result->status);
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
