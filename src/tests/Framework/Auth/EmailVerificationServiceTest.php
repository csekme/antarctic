<?php

declare(strict_types=1);

namespace Tests\Framework\Auth;

use Framework\Auth\EmailVerificationService;
use Framework\Auth\VerifyStatus;
use Framework\Dal;
use Framework\Token;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PDO;
use PHPUnit\Framework\TestCase;

final class EmailVerificationServiceTest extends TestCase
{
    private const APP_SECRET = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    private PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        putenv('APP_SECRET_KEY=' . self::APP_SECRET);
    }

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
        SQL);
        Dal::setConnection($this->pdo);
    }

    protected function tearDown(): void
    {
        Dal::setConnection(null);
    }

    public function testValidTokenActivatesUser(): void
    {
        $rawToken = bin2hex(random_bytes(16));
        $hash = (new Token($rawToken))->getHash();
        $this->pdo->prepare('INSERT INTO user (uuid, email, username, activation_hash, is_active) VALUES (?, ?, ?, ?, 0)')
            ->execute(['u-1', 'alice@example.com', 'alice', $hash]);

        $service = $this->newService();
        $result = $service->verify($rawToken);

        $this->assertSame(VerifyStatus::Ok, $result->status);
        $row = $this->pdo->query('SELECT is_active, activation_hash FROM user WHERE username = "alice"')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $row['is_active']);
        $this->assertNull($row['activation_hash']);
    }

    public function testUnknownTokenReturnsUnknown(): void
    {
        $service = $this->newService();
        $result = $service->verify(str_repeat('f', 32));

        $this->assertSame(VerifyStatus::Unknown, $result->status);
    }

    private function newService(): EmailVerificationService
    {
        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());
        return new EmailVerificationService($logger);
    }
}
