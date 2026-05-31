<?php

declare(strict_types=1);

namespace Tests\Framework\Controllers\Api\V1;

use Application\Dto\ConfirmTwoFactorRequest;
use Application\Dto\DisableTwoFactorRequest;
use Application\Dto\LoginRequest;
use Application\Dto\RegisterRequest;
use Application\Dto\VerifyEmailRequest;
use Framework\Auth\AuthenticatedUser;
use Framework\Controllers\Api\V1\AuthController;
use Framework\Dal;
use Framework\Repositories\TwoFactorRepository;
use Framework\Request;
use Framework\Response;
use Framework\Token;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integration-style coverage for the registration, email-verification and
 * 2FA enroll/confirm/disable endpoints. Uses an sqlite memory PDO injected
 * via Dal::setConnection — the same Dal/static finders the production code
 * uses, just pointed at an isolated schema.
 */
final class AuthControllerTest extends TestCase
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
                uuid TEXT,
                username TEXT,
                firstname TEXT,
                lastname TEXT,
                email TEXT,
                password_hash TEXT,
                activation_hash TEXT,
                is_active INTEGER DEFAULT 0,
                password_reset_hash TEXT,
                password_reset_expires_at TEXT,
                created_at TEXT,
                updated_at TEXT
            );
            CREATE TABLE two_factor (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                method TEXT NOT NULL,
                secret_key TEXT,
                passcode TEXT,
                enabled INTEGER DEFAULT 0,
                passcode_expired_at TEXT
            );
            CREATE TABLE user_role (
                user_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL
            );
            CREATE TABLE role (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT
            );
        SQL);
        Dal::setConnection($this->pdo);
    }

    protected function tearDown(): void
    {
        Dal::setConnection(null);
    }

    public function testRegisterCreatesInactiveUserAndReturnsVerificationLink(): void
    {
        $controller = $this->newController();
        $response = $controller->register(new RegisterRequest(
            email: 'alice@example.com',
            username: 'alice',
            password: 'Secret123!',
            password_confirm: 'Secret123!',
        ));

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertTrue($body['requires_verification']);
        $this->assertArrayHasKey('verification_link', $body);
        $this->assertStringContainsString('token=', $body['verification_link']);

        $row = $this->pdo->query('SELECT email, is_active FROM user WHERE username = "alice"')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('alice@example.com', $row['email']);
        $this->assertSame(0, (int) $row['is_active']);
    }

    public function testRegisterReturns409WhenEmailAlreadyExists(): void
    {
        $controller = $this->newController();
        $controller->register(new RegisterRequest(
            email: 'alice@example.com', username: 'alice',
            password: 'Secret123!', password_confirm: 'Secret123!',
        ));

        $response = $controller->register(new RegisterRequest(
            email: 'alice@example.com', username: 'alice2',
            password: 'Secret123!', password_confirm: 'Secret123!',
        ));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('email_already_registered', $this->decode($response)['code']);
    }

    public function testRegisterReturns409WhenUsernameTaken(): void
    {
        $controller = $this->newController();
        $controller->register(new RegisterRequest(
            email: 'alice@example.com', username: 'alice',
            password: 'Secret123!', password_confirm: 'Secret123!',
        ));

        $response = $controller->register(new RegisterRequest(
            email: 'alice2@example.com', username: 'alice',
            password: 'Secret123!', password_confirm: 'Secret123!',
        ));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('username_taken', $this->decode($response)['code']);
    }

    public function testVerifyEmailActivatesTheUser(): void
    {
        $controller = $this->newController();
        $register = $controller->register(new RegisterRequest(
            email: 'alice@example.com', username: 'alice',
            password: 'Secret123!', password_confirm: 'Secret123!',
        ));
        $token = $this->extractToken($this->decode($register)['verification_link']);

        $response = $controller->verifyEmail(new VerifyEmailRequest(token: $token));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($this->decode($response)['verified']);
        $row = $this->pdo->query('SELECT is_active, activation_hash FROM user WHERE username = "alice"')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $row['is_active']);
        $this->assertNull($row['activation_hash']);
    }

    public function testVerifyEmailReturns404ForUnknownToken(): void
    {
        $controller = $this->newController();
        $response = $controller->verifyEmail(new VerifyEmailRequest(token: str_repeat('a', 32)));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('token_unknown_or_expired', $this->decode($response)['code']);
    }

    public function testLoginRejectsInactiveUserWith403EmailNotVerified(): void
    {
        $controller = $this->newController();
        $controller->register(new RegisterRequest(
            email: 'alice@example.com', username: 'alice',
            password: 'Secret123!', password_confirm: 'Secret123!',
        ));

        $response = $controller->login(new LoginRequest(email: 'alice@example.com', password: 'Secret123!'));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('email_not_verified', $this->decode($response)['code']);
    }

    public function testLoginReturns401ForWrongPassword(): void
    {
        $controller = $this->newController();
        $controller->register(new RegisterRequest(
            email: 'alice@example.com', username: 'alice',
            password: 'Secret123!', password_confirm: 'Secret123!',
        ));
        // Activate so we know 401 isn't coming from the inactive branch.
        $this->pdo->exec('UPDATE user SET is_active = 1 WHERE username = "alice"');

        $response = $controller->login(new LoginRequest(email: 'alice@example.com', password: 'wrong'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testEnrollTwoFactorReturnsSecretAndQr(): void
    {
        $controller = $this->newController();
        $userId = $this->createAndActivateUser($controller);
        $this->authenticate($controller, $userId);

        $response = $controller->enrollTwoFactor();

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertArrayHasKey('secret', $body);
        $this->assertStringStartsWith('otpauth://totp/Antarctic', $body['otpauth_uri']);
        $this->assertStringStartsWith('data:image/', $body['qr_data_uri']);

        $row = $this->pdo->query('SELECT enabled FROM two_factor WHERE user_id = ' . $userId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(0, (int) $row['enabled']);
    }

    public function testEnrollTwoFactorReturns409WhenAlreadyEnabled(): void
    {
        $controller = $this->newController();
        $userId = $this->createAndActivateUser($controller);
        $this->authenticate($controller, $userId);
        (new TwoFactorRepository($this->pdo))->enroll($userId, TwoFactorRepository::METHOD_APP, 'JBSWY3DPEHPK3PXP', enabled: true);

        $response = $controller->enrollTwoFactor();

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('2fa_already_enabled', $this->decode($response)['code']);
    }

    public function testConfirmTwoFactorEnablesOnValidCode(): void
    {
        $controller = $this->newController();
        $userId = $this->createAndActivateUser($controller);
        $this->authenticate($controller, $userId);
        (new TwoFactorRepository($this->pdo))->enroll($userId, TwoFactorRepository::METHOD_APP, 'JBSWY3DPEHPK3PXP', enabled: false);
        $controller->setTotpVerifier(static fn (string $s, string $c): bool => $c === '654321');

        $response = $controller->confirmTwoFactor(new ConfirmTwoFactorRequest(code: '654321'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($this->decode($response)['enabled']);
        $row = $this->pdo->query('SELECT enabled FROM two_factor WHERE user_id = ' . $userId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $row['enabled']);
    }

    public function testConfirmTwoFactorRejectsInvalidCode(): void
    {
        $controller = $this->newController();
        $userId = $this->createAndActivateUser($controller);
        $this->authenticate($controller, $userId);
        (new TwoFactorRepository($this->pdo))->enroll($userId, TwoFactorRepository::METHOD_APP, 'JBSWY3DPEHPK3PXP', enabled: false);
        $controller->setTotpVerifier(static fn (): bool => false);

        $response = $controller->confirmTwoFactor(new ConfirmTwoFactorRequest(code: '000000'));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('invalid_code', $this->decode($response)['code']);
    }

    public function testConfirmTwoFactorReturns409WhenEnrollmentNotStarted(): void
    {
        $controller = $this->newController();
        $userId = $this->createAndActivateUser($controller);
        $this->authenticate($controller, $userId);

        $response = $controller->confirmTwoFactor(new ConfirmTwoFactorRequest(code: '123456'));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('enrollment_not_started', $this->decode($response)['code']);
    }

    public function testDisableTwoFactorRequiresCorrectPassword(): void
    {
        $controller = $this->newController();
        $userId = $this->createAndActivateUser($controller);
        $this->authenticate($controller, $userId);
        (new TwoFactorRepository($this->pdo))->enroll($userId, TwoFactorRepository::METHOD_APP, 'JBSWY3DPEHPK3PXP', enabled: true);

        $reject = $controller->disableTwoFactor(new DisableTwoFactorRequest(password: 'wrong'));
        $this->assertSame(401, $reject->getStatusCode());
        $this->assertSame('password_required', $this->decode($reject)['code']);

        $accept = $controller->disableTwoFactor(new DisableTwoFactorRequest(password: 'Secret123!'));
        $this->assertSame(200, $accept->getStatusCode());
        $this->assertFalse($this->decode($accept)['enabled']);
        $row = $this->pdo->query('SELECT enabled FROM two_factor WHERE user_id = ' . $userId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(0, (int) $row['enabled']);
    }

    public function testMeIncludesTwoFactorMethods(): void
    {
        $controller = $this->newController();
        $userId = $this->createAndActivateUser($controller);
        $this->authenticate($controller, $userId);
        (new TwoFactorRepository($this->pdo))->enroll($userId, TwoFactorRepository::METHOD_APP, 'JBSWY3DPEHPK3PXP', enabled: true);

        $response = $controller->me();

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertSame(['app'], $body['two_factor']['methods']);
    }

    public function testVerifyEmailHashMatchesTokenHmac(): void
    {
        // Defensive: belts-and-braces that our raw-token → hash → activate
        // pipeline is consistent end-to-end (i.e. the SAME APP_SECRET_KEY).
        $controller = $this->newController();
        $register = $controller->register(new RegisterRequest(
            email: 'alice@example.com', username: 'alice',
            password: 'Secret123!', password_confirm: 'Secret123!',
        ));
        $token = $this->extractToken($this->decode($register)['verification_link']);

        $storedHash = $this->pdo->query('SELECT activation_hash FROM user WHERE username = "alice"')->fetchColumn();
        $this->assertSame((new Token($token))->getHash(), $storedHash);
    }

    private function newController(): AuthController
    {
        $controller = new AuthController(
            new Request('', 'POST', [], [], [], [], []),
            new Response(),
        );
        // The default Mail::sendHtmlMessage reads Config::get_config()["smtp"]
        // which is null in tests (no application.json). Replace with a no-op spy.
        $controller->setMailer(static fn (): bool => true);
        // The default TwoFactor uses BaconQrCodeProvider which needs the imagick
        // extension — not installed on the test host. Replace with a deterministic
        // stub that returns a known secret + a placeholder data URI.
        $controller->setTwoFactorEnroller(static fn (): array => [
            'secret' => 'JBSWY3DPEHPK3PXP',
            'qr_data_uri' => 'data:image/png;base64,iVBORw0KGgo=',
        ]);
        return $controller;
    }

    private function createAndActivateUser(AuthController $controller): int
    {
        $controller->register(new RegisterRequest(
            email: 'alice@example.com', username: 'alice',
            password: 'Secret123!', password_confirm: 'Secret123!',
        ));
        $this->pdo->exec('UPDATE user SET is_active = 1 WHERE username = "alice"');
        return (int) $this->pdo->query('SELECT id FROM user WHERE username = "alice"')->fetchColumn();
    }

    private function authenticate(AuthController $controller, int $userId): void
    {
        // The controller reads request->authUser; the dispatcher would normally
        // set this from a valid Bearer token. We do it directly.
        $request = $this->getController_request($controller);
        $request->authUser = new AuthenticatedUser(id: $userId, roles: []);
    }

    private function getController_request(AuthController $controller): Request
    {
        $ref = new \ReflectionProperty(\Framework\AbstractController::class, 'request');
        $ref->setAccessible(true);
        /** @var Request $req */
        $req = $ref->getValue($controller);
        return $req;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        /** @var array<string, mixed> $body */
        $body = json_decode($response->getBody(), true);
        return $body;
    }

    private function extractToken(string $link): string
    {
        $query = parse_url($link, PHP_URL_QUERY) ?: '';
        parse_str($query, $params);
        return (string) ($params['token'] ?? '');
    }
}
