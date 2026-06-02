<?php

declare(strict_types=1);

namespace Tests\Framework\Auth;

use Framework\Auth\ConfirmStatus;
use Framework\Auth\DisableStatus;
use Framework\Auth\EnrollStatus;
use Framework\Auth\PasswordVerifier;
use Framework\Auth\TwoFactorSetupService;
use Framework\Dal;
use Framework\Repositories\TwoFactorRepository;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PDO;
use PHPUnit\Framework\TestCase;

final class TwoFactorSetupServiceTest extends TestCase
{
    private PDO $pdo;
    private TwoFactorRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE user (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT, username TEXT, firstname TEXT, lastname TEXT,
                email TEXT, password_hash TEXT, activation_hash TEXT,
                is_active INTEGER DEFAULT 1,
                password_reset_hash TEXT, password_reset_expires_at TEXT,
                created_at TEXT, updated_at TEXT
            );
            CREATE TABLE two_factor (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL, method TEXT NOT NULL,
                secret_key TEXT, passcode TEXT, enabled INTEGER DEFAULT 0,
                passcode_expired_at TEXT
            );
        SQL);
        Dal::setConnection($this->pdo);
        $this->repo = new TwoFactorRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        Dal::setConnection(null);
    }

    public function testEnrollGeneratesSecret(): void
    {
        $service = $this->newService();
        $result = $service->enroll(userId: 1, email: 'alice@example.com');

        $this->assertSame(EnrollStatus::Started, $result->status);
        $this->assertSame('JBSWY3DPEHPK3PXP', $result->secret);
        $this->assertStringStartsWith('otpauth://totp/Antarctic', (string) $result->otpauthUri);
    }

    public function testEnrollOnAlreadyEnabledReturns409Equivalent(): void
    {
        $this->repo->enroll(1, 'app', 'EXISTING', enabled: true);
        $service = $this->newService();

        $result = $service->enroll(1, 'alice@example.com');

        $this->assertSame(EnrollStatus::AlreadyEnabled, $result->status);
    }

    public function testConfirmEnablesOnValidCode(): void
    {
        $this->repo->enroll(1, 'app', 'JBSWY3DPEHPK3PXP', enabled: false);
        $service = $this->newService();
        $service->setTotpVerifier(static fn (string $s, string $c): bool => $c === '654321');

        $result = $service->confirm(1, '654321');

        $this->assertSame(ConfirmStatus::Enabled, $result->status);
        $row = $this->pdo->query('SELECT enabled FROM two_factor WHERE user_id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $row['enabled']);
    }

    public function testConfirmRejectsInvalidCode(): void
    {
        $this->repo->enroll(1, 'app', 'JBSWY3DPEHPK3PXP', enabled: false);
        $service = $this->newService();
        $service->setTotpVerifier(static fn (): bool => false);

        $result = $service->confirm(1, '000000');

        $this->assertSame(ConfirmStatus::InvalidCode, $result->status);
    }

    public function testConfirmReturnsNotStartedWithoutEnroll(): void
    {
        $service = $this->newService();
        $result = $service->confirm(1, '123456');

        $this->assertSame(ConfirmStatus::NotStarted, $result->status);
    }

    public function testDisableRequiresCorrectPassword(): void
    {
        $hash = password_hash('Secret123!', PASSWORD_DEFAULT);
        $this->pdo->prepare('INSERT INTO user (uuid, email, username, password_hash, is_active) VALUES (?, ?, ?, ?, 1)')
            ->execute(['u-1', 'a@x.com', 'alice', $hash]);
        $this->repo->enroll(1, 'app', 'SECRET', enabled: true);
        $service = $this->newService();

        $this->assertSame(DisableStatus::WrongPassword, $service->disable(1, 'wrong')->status);
        $this->assertSame(DisableStatus::Disabled, $service->disable(1, 'Secret123!')->status);
        $row = $this->pdo->query('SELECT enabled FROM two_factor WHERE user_id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(0, (int) $row['enabled']);
    }

    public function testDisableReturnsUserMissingForUnknownUser(): void
    {
        $service = $this->newService();
        $result = $service->disable(9999, 'whatever');

        $this->assertSame(DisableStatus::UserMissing, $result->status);
    }

    private function newService(): TwoFactorSetupService
    {
        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());
        return new TwoFactorSetupService(
            twoFactorRepo: $this->repo,
            logger: $logger,
            totpVerifier: static fn (): bool => true,
            enroller: static fn (): array => ['secret' => 'JBSWY3DPEHPK3PXP', 'qr_data_uri' => 'data:image/png;base64,xx'],
        );
    }
}
