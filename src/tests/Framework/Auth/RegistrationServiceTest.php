<?php

declare(strict_types=1);

namespace Tests\Framework\Auth;

use Application\Dto\RegisterRequest;
use Framework\Auth\RegistrationService;
use Framework\Auth\RegistrationStatus;
use Framework\Dal;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PDO;
use PHPUnit\Framework\TestCase;

final class RegistrationServiceTest extends TestCase
{
    private const APP_SECRET = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    private PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        putenv('APP_SECRET_KEY=' . self::APP_SECRET);
        putenv('APP_VERIFY_EMAIL_URL=http://localhost:5173/verify-email');
        putenv('APP_EXPOSE_VERIFICATION_LINK=1');
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

    public function testCreatesUserAndExposesVerificationLink(): void
    {
        $service = $this->newService();
        $result = $service->register(new RegisterRequest(
            email: 'alice@example.com', username: 'alice',
            password: 'Secret123!', password_confirm: 'Secret123!',
        ));

        $this->assertSame(RegistrationStatus::Created, $result->status);
        $this->assertNotNull($result->userId);
        $this->assertNotNull($result->verificationLink);
        $this->assertStringContainsString('token=', $result->verificationLink);
    }

    public function testDuplicateEmail(): void
    {
        $service = $this->newService();
        $service->register(new RegisterRequest('alice@example.com', 'alice', 'Secret123!', 'Secret123!'));

        $result = $service->register(new RegisterRequest('alice@example.com', 'alice2', 'Secret123!', 'Secret123!'));

        $this->assertSame(RegistrationStatus::EmailTaken, $result->status);
    }

    public function testDuplicateUsername(): void
    {
        $service = $this->newService();
        $service->register(new RegisterRequest('alice@example.com', 'alice', 'Secret123!', 'Secret123!'));

        $result = $service->register(new RegisterRequest('alice2@example.com', 'alice', 'Secret123!', 'Secret123!'));

        $this->assertSame(RegistrationStatus::UsernameTaken, $result->status);
    }

    public function testMailerSpyIsCalledOnSuccess(): void
    {
        $calls = [];
        $service = $this->newService();
        $service->setMailer(static function (string $to, string $subject, string $html) use (&$calls): bool {
            $calls[] = compact('to', 'subject', 'html');
            return true;
        });

        $service->register(new RegisterRequest('alice@example.com', 'alice', 'Secret123!', 'Secret123!'));

        $this->assertCount(1, $calls);
        $this->assertSame('alice@example.com', $calls[0]['to']);
        $this->assertStringContainsString('token=', $calls[0]['html']);
    }

    private function newService(): RegistrationService
    {
        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());
        $service = new RegistrationService($logger);
        $service->setMailer(static fn (): bool => true);
        return $service;
    }
}
