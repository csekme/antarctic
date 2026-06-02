<?php

declare(strict_types=1);

namespace Tests\Framework\Auth;

use Framework\Auth\LoginService;
use Framework\Auth\LoginStatus;
use Framework\Auth\NativePasswordVerifier;
use Framework\Auth\PasswordVerifier;
use Framework\Dal;
use Framework\Repositories\TwoFactorRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class LoginServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE user (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT, username TEXT, firstname TEXT, lastname TEXT,
                email TEXT, password_hash TEXT, activation_hash TEXT,
                is_active INTEGER DEFAULT 0,
                password_reset_hash TEXT, password_reset_expires_at TEXT,
                created_at TEXT, updated_at TEXT
            );
            CREATE TABLE two_factor (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL, method TEXT NOT NULL,
                secret_key TEXT, passcode TEXT, enabled INTEGER DEFAULT 0,
                passcode_expired_at TEXT
            );
            CREATE TABLE user_role (user_id INTEGER NOT NULL, role_id INTEGER NOT NULL);
            CREATE TABLE role (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT);
        SQL);
        Dal::setConnection($this->pdo);
    }

    protected function tearDown(): void
    {
        Dal::setConnection(null);
    }

    public function testValidCredentialsReturnsOk(): void
    {
        $this->insertUser('alice@example.com', 'alice', 'Secret123!', isActive: 1);
        $service = $this->newService();

        $result = $service->attempt('alice@example.com', 'Secret123!');

        $this->assertSame(LoginStatus::Ok, $result->status);
        $this->assertNotNull($result->user);
        $this->assertSame('alice@example.com', $result->user->email);
    }

    public function testInactiveUserReturnsUnverified(): void
    {
        $this->insertUser('alice@example.com', 'alice', 'Secret123!', isActive: 0);
        $service = $this->newService();

        $result = $service->attempt('alice@example.com', 'Secret123!');

        $this->assertSame(LoginStatus::Unverified, $result->status);
    }

    public function testWrongPasswordReturnsInvalidCredentials(): void
    {
        $this->insertUser('alice@example.com', 'alice', 'Secret123!', isActive: 1);
        $service = $this->newService();

        $result = $service->attempt('alice@example.com', 'wrong');

        $this->assertSame(LoginStatus::InvalidCredentials, $result->status);
    }

    public function testUnknownEmailReturnsInvalidCredentials(): void
    {
        $service = $this->newService();

        $result = $service->attempt('nobody@example.com', 'whatever');

        $this->assertSame(LoginStatus::InvalidCredentials, $result->status);
    }

    public function testUserEnumerationMitigationCallsVerifyEvenWhenUserMissing(): void
    {
        $spy = new class implements PasswordVerifier {
            public int $calls = 0;
            public function verify(string $password, string $hash): bool
            {
                $this->calls++;
                return false;
            }
        };
        $service = new LoginService(new TwoFactorRepository($this->pdo), $spy);

        $service->attempt('nobody@example.com', 'whatever');

        $this->assertSame(1, $spy->calls, 'password_verify must be called on the missing-user branch to keep timing constant');
    }

    public function testTwoFactorEnabledReturnsTwoFactorRequired(): void
    {
        $userId = $this->insertUser('alice@example.com', 'alice', 'Secret123!', isActive: 1);
        (new TwoFactorRepository($this->pdo))->enroll($userId, TwoFactorRepository::METHOD_APP, 'SECRET', enabled: true);
        $service = $this->newService();

        $result = $service->attempt('alice@example.com', 'Secret123!');

        $this->assertSame(LoginStatus::TwoFactorRequired, $result->status);
        $this->assertSame(['app'], $result->methods);
    }

    private function newService(): LoginService
    {
        return new LoginService(new TwoFactorRepository($this->pdo), new NativePasswordVerifier());
    }

    private function insertUser(string $email, string $username, string $password, int $isActive): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO user (uuid, email, username, password_hash, is_active) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute(['uuid-' . $username, $email, $username, $hash, $isActive]);
        return (int) $this->pdo->lastInsertId();
    }
}
