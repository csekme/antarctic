<?php

declare(strict_types=1);

namespace Framework\Controllers\Api\V1;

use Application\Dto\ConfirmTwoFactorRequest;
use Application\Dto\DisableTwoFactorRequest;
use Application\Dto\LoginRequest;
use Application\Dto\RegisterRequest;
use Application\Dto\VerifyEmailRequest;
use Application\Dto\VerifyTwoFactorRequest;
use DomainException;
use Framework\AbstractController;
use Framework\Auth\AuthenticatedUser;
use Framework\Auth\JwtConfigFactory;
use Framework\Auth\RefreshTokenRepository;
use Framework\Auth\RequireAuth;
use Framework\Auth\SystemClock;
use Framework\Auth\TokenService;
use Framework\Auth\TwoFactorChallengeService;
use Framework\Controller;
use Framework\Dal;
use Framework\Http\RequestScheme;
use Framework\Logging\LoggerFactory;
use Framework\Mail;
use Framework\Models\AbstractUser;
use Framework\Models\User;
use Framework\Path;
use Framework\Repositories\TwoFactorRepository;
use Framework\Response;
use Framework\Token;
use Framework\TwoFactor;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * JWT-alapú auth endpointok.
 *
 *   POST   /api/v1/auth/register             — új user (is_active=0) + activation token.
 *   POST   /api/v1/auth/verify-email         — aktiváció a raw token-nel.
 *   POST   /api/v1/auth/login                — email + password → access + refresh,
 *                                              vagy 2FA challenge, ha a usernek engedélyezett TOTP-je van.
 *   POST   /api/v1/auth/2fa/verify           — challenge_token + code → access + refresh.
 *   POST   /api/v1/auth/2fa/enroll           — TOTP secret + QR (RequireAuth).
 *   POST   /api/v1/auth/2fa/enroll/confirm   — első kód → enabled (RequireAuth).
 *   POST   /api/v1/auth/2fa/disable          — password re-auth → kikapcsol (RequireAuth).
 *   POST   /api/v1/auth/refresh              — rotated access + refresh (double-submit CSRF).
 *   POST   /api/v1/auth/logout               — revoke + cookie clear.
 *   GET    /api/v1/auth/me                   — aktuális user + 2FA állapot (RequireAuth).
 */
class AuthController extends Controller
{
    private const REFRESH_COOKIE = '__Host-refresh';
    private const CSRF_COOKIE = 'csrf_token';
    private const REFRESH_COOKIE_PATH = '/api/v1/auth';

    private TokenService $tokenService;
    private TwoFactorChallengeService $challengeService;
    /** @var callable(string $secret, string $code): bool */
    private $totpVerifier;
    /** @var callable(string $to, string $subject, string $html): bool|string */
    private $mailer;
    /** @var callable(): array{secret: string, qr_data_uri: string} */
    private $twoFactorEnroller;
    private TwoFactorRepository $twoFactorRepo;
    private LoggerInterface $logger;
    private int $accessTtl;
    private int $refreshTtl;
    private int $challengeTtl;

    public function __construct(
        \Framework\Request $request,
        \Framework\Response $response,
        array $route_params = [],
    ) {
        parent::__construct($request, $response, $route_params);
        $this->boot();
    }

    /** Tesztből / containerből cserélhető. */
    public function setTokenService(TokenService $service, int $accessTtl, int $refreshTtl): void
    {
        $this->tokenService = $service;
        $this->accessTtl = $accessTtl;
        $this->refreshTtl = $refreshTtl;
    }

    public function setChallengeService(TwoFactorChallengeService $service): void
    {
        $this->challengeService = $service;
        $this->challengeTtl = $service->ttl();
    }

    /**
     * @param callable(string, string): bool $verifier
     */
    public function setTotpVerifier(callable $verifier): void
    {
        $this->totpVerifier = $verifier;
    }

    /**
     * Tesztből cserélhető mailer. A signature `Mail::sendHtmlMessage`-et követi.
     *
     * @param callable(string, string, string): (bool|string) $mailer
     */
    public function setMailer(callable $mailer): void
    {
        $this->mailer = $mailer;
    }

    /**
     * Tesztből cserélhető 2FA secret + QR generátor. A return tömbnek
     * `secret` és `qr_data_uri` kulcsa van.
     *
     * @param callable(): array{secret: string, qr_data_uri: string} $enroller
     */
    public function setTwoFactorEnroller(callable $enroller): void
    {
        $this->twoFactorEnroller = $enroller;
    }

    public function setTwoFactorRepository(TwoFactorRepository $repo): void
    {
        $this->twoFactorRepo = $repo;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    private function boot(): void
    {
        $config = require dirname(__DIR__, 4) . '/config/jwt.php';
        $this->accessTtl = (int) $config['access_ttl'];
        $this->refreshTtl = (int) $config['refresh_ttl'];
        $this->challengeTtl = (int) ($config['challenge_ttl'] ?? 300);
        $jwtConfig = JwtConfigFactory::create($config);
        $clock = new SystemClock();
        $this->tokenService = new TokenService(
            jwt: $jwtConfig,
            refreshTokens: new RefreshTokenRepository(Dal::getConnection()),
            clock: $clock,
            issuer: $config['issuer'],
            audience: $config['audience'],
            accessTtl: $this->accessTtl,
            refreshTtl: $this->refreshTtl,
            clockSkew: (int) $config['clock_skew'],
        );
        $this->challengeService = new TwoFactorChallengeService(
            jwt: $jwtConfig,
            clock: $clock,
            issuer: $config['issuer'],
            audience: $config['audience'],
            ttl: $this->challengeTtl,
            clockSkew: (int) $config['clock_skew'],
        );
        $this->totpVerifier = static function (string $secret, string $code): bool {
            return (new TwoFactor())->verifyCode($secret, $code);
        };
        $this->mailer = static function (string $to, string $subject, string $html): bool|string {
            return Mail::sendHtmlMessage($to, $subject, $html);
        };
        $this->twoFactorEnroller = static function (): array {
            $tfa = new TwoFactor();
            $secret = $tfa->generateSecretKey();
            return ['secret' => $secret, 'qr_data_uri' => $tfa->getQRCodeImageAsDataUri($secret)];
        };
        $this->twoFactorRepo = new TwoFactorRepository(Dal::getConnection());
        $this->logger = LoggerFactory::fromEnv();
    }

    #[Path(path: '/api/v1/auth/login', method: 'POST')]
    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'Authenticate with email + password.',
        tags: ['auth'],
        security: [],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Access + refresh issued, or 2FA challenge required.'),
            new OA\Response(response: 401, description: 'Invalid credentials.'),
            new OA\Response(response: 422, description: 'Body failed validation.'),
        ],
    )]
    public function login(LoginRequest $body): Response
    {
        // User-enumeration mitigáció: password_verify mindig fusson, akkor is, ha
        // a user nem létezik vagy nem aktív — különben a támadó a 401 vs. 403
        // visszajelzésből kikövetkeztethetné a létezést.
        $user = User::findByUsernameOrEmail($body->email);
        $passwordOk = false;
        if ($user instanceof AbstractUser && is_string($user->password_hash ?? null)) {
            $passwordOk = password_verify($body->password, (string) $user->password_hash);
        } else {
            // Dummy verify, hogy a timing ne árulja el a hiányzó usert.
            password_verify($body->password, '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidi');
        }
        if (!$passwordOk || !$user instanceof AbstractUser) {
            return $this->problem(401, 'Invalid credentials.');
        }
        if (!($user->is_active ?? false)) {
            return $this->problem(403, 'Email not verified.', 'email_not_verified');
        }

        // A cleanSecurityFields a sikeres login után fut le (mivel az authenticate-et nem hívjuk).
        unset($user->password_hash, $user->activation_hash, $user->password_reset_hash);

        $methods = $this->twoFactorRepo->enabledMethods((int) $user->id);
        if ($methods !== []) {
            $challenge = $this->challengeService->issueChallenge((int) $user->id);
            return Response::json([
                'requires' => '2fa',
                'challenge_token' => $challenge,
                'methods' => $methods,
                'expires_in' => $this->challengeTtl,
            ]);
        }

        return $this->issueSession($user);
    }

    #[Path(path: '/api/v1/auth/2fa/verify', method: 'POST')]
    #[OA\Post(
        path: '/api/v1/auth/2fa/verify',
        summary: 'Complete the 2FA challenge with a TOTP code.',
        tags: ['auth'],
        security: [],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/VerifyTwoFactorRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Access + refresh issued.'),
            new OA\Response(response: 401, description: 'Challenge expired, code wrong, or 2FA not enabled.'),
            new OA\Response(response: 422, description: 'Body failed validation.'),
        ],
    )]
    public function verifyTwoFactor(VerifyTwoFactorRequest $body): Response
    {
        try {
            $userId = $this->challengeService->verifyChallenge($body->challenge_token);
        } catch (DomainException $e) {
            return $this->problem(401, $e->getMessage());
        }

        $user = User::findByID($userId);
        if ($user === false || !($user->is_active ?? true)) {
            return $this->problem(401, 'User is inactive.');
        }

        $row = $this->twoFactorRepo->findByUserIdAndMethod($userId, TwoFactorRepository::METHOD_APP);
        if ($row === null || (int) ($row['enabled'] ?? 0) !== 1) {
            return $this->problem(401, '2FA is not enabled for this user.');
        }

        $secret = (string) ($row['secret_key'] ?? '');
        if ($secret === '' || !($this->totpVerifier)($secret, $body->code)) {
            return $this->problem(401, 'Invalid 2FA code.');
        }

        return $this->issueSession($user);
    }

    #[Path(path: '/api/v1/auth/refresh', method: 'POST')]
    #[OA\Post(
        path: '/api/v1/auth/refresh',
        summary: 'Rotate the refresh cookie and issue a fresh access token.',
        tags: ['auth'],
        security: [],
        responses: [
            new OA\Response(response: 200, description: 'New access + rotated refresh cookie.'),
            new OA\Response(response: 401, description: 'Missing, unknown, or expired refresh cookie.'),
            new OA\Response(response: 403, description: 'CSRF double-submit token mismatch.'),
        ],
    )]
    public function refresh(): Response
    {
        $refresh = $_COOKIE[self::REFRESH_COOKIE] ?? null;
        if (!is_string($refresh) || $refresh === '') {
            return $this->problem(401, 'Refresh cookie missing.');
        }

        $headerCsrf = $this->request->server['HTTP_X_CSRF_TOKEN'] ?? null;
        $cookieCsrf = $_COOKIE[self::CSRF_COOKIE] ?? null;
        if (!is_string($headerCsrf) || !is_string($cookieCsrf) || !hash_equals($cookieCsrf, $headerCsrf)) {
            return $this->problem(403, 'CSRF token mismatch.');
        }

        // A user-id-t az aktuális refresh token utolsó kibocsátójához kötjük.
        // A repository hashen keresztül azonosít, így a kliens nem manipulálhatja
        // a user-id-t. A roles-t friss DB lookupból olvassuk.
        $hash = hash('sha256', $refresh);
        $existing = (new RefreshTokenRepository(Dal::getConnection()))->findByHash($hash);
        if ($existing === null) {
            return $this->problem(401, 'Unknown refresh token.');
        }

        $userId = (int) $existing['user_id'];
        $user = User::findByID($userId);
        if ($user === false || !($user->is_active ?? true)) {
            return $this->problem(401, 'User is inactive.');
        }

        try {
            $rotated = $this->tokenService->rotateRefresh(
                refreshToken: $refresh,
                userId: $userId,
                roles: $user->getRoles(),
                userAgent: $this->request->server['HTTP_USER_AGENT'] ?? null,
                ip: $this->request->server['REMOTE_ADDR'] ?? null,
            );
        } catch (DomainException $e) {
            return $this->problem(401, $e->getMessage());
        }

        $this->setRefreshCookie($rotated['refresh_token']);
        $csrf = $this->rotateCsrfCookie();

        return Response::json([
            'access_token' => $rotated['access_token'],
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTtl,
            'csrf_token' => $csrf,
        ]);
    }

    #[Path(path: '/api/v1/auth/logout', method: 'POST')]
    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'Revoke the current refresh token and clear the cookies.',
        tags: ['auth'],
        security: [],
        responses: [
            new OA\Response(response: 200, description: 'Logged out (idempotent).'),
        ],
    )]
    public function logout(): Response
    {
        $refresh = $_COOKIE[self::REFRESH_COOKIE] ?? null;
        if (is_string($refresh) && $refresh !== '') {
            $this->tokenService->revokeRefresh($refresh);
        }
        $this->clearRefreshCookie();
        $this->clearCsrfCookie();

        return Response::json(['ok' => true]);
    }

    #[Path(path: '/api/v1/auth/me', method: 'GET')]
    #[RequireAuth]
    #[OA\Get(
        path: '/api/v1/auth/me',
        summary: 'Return the currently authenticated user.',
        tags: ['auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Authenticated user payload.'),
            new OA\Response(response: 401, description: 'Missing or invalid Bearer token.'),
            new OA\Response(response: 404, description: 'Authenticated user no longer exists.'),
        ],
    )]
    public function me(): Response
    {
        $user = $this->request->authUser;
        if (!$user instanceof AuthenticatedUser) {
            return $this->problem(401, 'Not authenticated.');
        }
        $entity = User::findByID($user->id);
        if ($entity === false) {
            return $this->problem(404, 'User not found.');
        }

        return Response::json([
            'id' => $user->id,
            'email' => $entity->email ?? null,
            'username' => $entity->username ?? null,
            'roles' => $user->roles,
            'two_factor' => [
                'methods' => $this->twoFactorRepo->enabledMethods((int) $user->id),
            ],
        ]);
    }

    #[Path(path: '/api/v1/auth/register', method: 'POST')]
    #[OA\Post(
        path: '/api/v1/auth/register',
        summary: 'Register a new user. The account is created inactive; an email-verification step is required before login.',
        tags: ['auth'],
        security: [],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterRequest')),
        responses: [
            new OA\Response(response: 201, description: 'User created; verification required.'),
            new OA\Response(response: 409, description: 'Email or username already taken.'),
            new OA\Response(response: 422, description: 'Body failed validation.'),
        ],
    )]
    public function register(RegisterRequest $body): Response
    {
        if (User::findByEmail($body->email) !== false) {
            return $this->problem(409, 'Email already registered.', 'email_already_registered');
        }
        if (User::findByUsername($body->username) !== false) {
            return $this->problem(409, 'Username already taken.', 'username_taken');
        }

        // A Dal::__construct UUID-t generál; a User::save a password_hash-t és
        // az activation_hash-t (Token HMAC) készíti, és $this->activation_token-en
        // hagyja a raw értéket — ezt használjuk a verify-linkben.
        // A User::save() belül a User::validate() fut le; az password_confirm-ot
        // a Model is összeveti a password-dal, ezért mindkettőt be kell állítani,
        // hogy a Model-szintű duplikált validáció ne állítsa meg a save-et.
        $user = new User();
        $user->email = $body->email;
        $user->username = $body->username;
        $user->password = $body->password;
        $user->password_confirm = $body->password;
        $user->firstname = $body->firstname;
        $user->lastname = $body->lastname;

        try {
            $saved = $user->save();
        } catch (Throwable $e) {
            $this->logger->error('auth.register.save_failed', ['error' => $e->getMessage()]);
            return $this->problem(500, 'Could not create user.', 'register_failed');
        }
        if (!$saved) {
            $this->logger->error('auth.register.save_failed', ['errors' => $user->errors ?? []]);
            return $this->problem(500, 'Could not create user.', 'register_failed');
        }

        $rawToken = (string) ($user->activation_token ?? '');
        $verifyBase = (string) (getenv('APP_VERIFY_EMAIL_URL') ?: '');
        $verifyLink = $verifyBase !== '' && $rawToken !== ''
            ? $verifyBase . (str_contains($verifyBase, '?') ? '&' : '?') . 'token=' . $rawToken
            : null;

        // Email küldés best-effort; ha SMTP nincs konfigurálva, csak logoljuk.
        if ($verifyLink !== null) {
            try {
                $html = sprintf(
                    '<p>Hi %s,</p><p>Click the link below to verify your email:</p><p><a href="%s">%s</a></p>',
                    htmlspecialchars($body->username, ENT_QUOTES | ENT_HTML5),
                    htmlspecialchars($verifyLink, ENT_QUOTES | ENT_HTML5),
                    htmlspecialchars($verifyLink, ENT_QUOTES | ENT_HTML5),
                );
                ($this->mailer)($body->email, 'Verify your Antarctic account', $html);
            } catch (Throwable $e) {
                $this->logger->warning('auth.register.mail_failed', ['error' => $e->getMessage()]);
            }
        }

        $persisted = User::findByEmail($body->email);
        $userId = $persisted instanceof AbstractUser ? (int) $persisted->id : 0;
        $this->logger->info('auth.register', [
            'user_id' => $userId,
            'ip' => $this->request->server['REMOTE_ADDR'] ?? null,
            'ua' => $this->request->server['HTTP_USER_AGENT'] ?? null,
        ]);

        $payload = [
            'user' => [
                'id' => $userId,
                'email' => $body->email,
                'username' => $body->username,
            ],
            'requires_verification' => true,
        ];
        if (filter_var(getenv('APP_EXPOSE_VERIFICATION_LINK') ?: '0', FILTER_VALIDATE_BOOL) && $verifyLink !== null) {
            $payload['verification_link'] = $verifyLink;
        }

        return Response::json($payload, 201);
    }

    #[Path(path: '/api/v1/auth/verify-email', method: 'POST')]
    #[OA\Post(
        path: '/api/v1/auth/verify-email',
        summary: 'Activate a newly registered account by submitting the raw activation token.',
        tags: ['auth'],
        security: [],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/VerifyEmailRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Account activated.'),
            new OA\Response(response: 404, description: 'Token unknown or already used.'),
            new OA\Response(response: 422, description: 'Body failed validation.'),
        ],
    )]
    public function verifyEmail(VerifyEmailRequest $body): Response
    {
        try {
            $hash = (new Token($body->token))->getHash();
        } catch (Throwable $e) {
            $this->logger->error('auth.verify_email.hash_failed', ['error' => $e->getMessage()]);
            return $this->problem(500, 'Server misconfiguration.', 'verify_misconfigured');
        }

        $pdo = Dal::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM user WHERE activation_hash = :h');
        $stmt->bindValue(':h', $hash);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            $this->logger->info('auth.verify_email.fail', ['reason' => 'unknown_token']);
            return $this->problem(404, 'Token unknown or expired.', 'token_unknown_or_expired');
        }

        User::activateByActivationHash($hash);
        $this->logger->info('auth.verify_email.success', ['user_id' => (int) $row['id']]);

        return Response::json(['verified' => true]);
    }

    #[Path(path: '/api/v1/auth/2fa/enroll', method: 'POST')]
    #[RequireAuth]
    #[OA\Post(
        path: '/api/v1/auth/2fa/enroll',
        summary: 'Start TOTP enrollment for the current user. Returns a secret + QR data URI to scan; the enrollment is not yet active until /2fa/enroll/confirm.',
        tags: ['auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Secret + QR returned.'),
            new OA\Response(response: 401, description: 'Missing or invalid Bearer token.'),
            new OA\Response(response: 409, description: '2FA already enabled.'),
        ],
    )]
    public function enrollTwoFactor(): Response
    {
        $authUser = $this->request->authUser;
        if (!$authUser instanceof AuthenticatedUser) {
            return $this->problem(401, 'Not authenticated.');
        }
        $userId = (int) $authUser->id;

        $existing = $this->twoFactorRepo->findByUserIdAndMethod($userId, TwoFactorRepository::METHOD_APP);
        if ($existing !== null && (int) ($existing['enabled'] ?? 0) === 1) {
            return $this->problem(409, '2FA already enabled.', '2fa_already_enabled');
        }

        $user = User::findByID($userId);
        $email = $user instanceof AbstractUser ? (string) ($user->email ?? '') : '';

        try {
            $generated = ($this->twoFactorEnroller)();
            $secret = $generated['secret'];
            $qr = $generated['qr_data_uri'];
        } catch (Throwable $e) {
            $this->logger->error('auth.2fa.enroll.failed', ['error' => $e->getMessage()]);
            return $this->problem(500, 'Could not generate 2FA secret.', '2fa_enroll_failed');
        }

        $this->twoFactorRepo->enroll($userId, TwoFactorRepository::METHOD_APP, $secret, enabled: false);

        $issuer = 'Antarctic Framework';
        $otpauth = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            rawurlencode($email),
            rawurlencode($secret),
            rawurlencode($issuer),
        );

        $this->logger->info('auth.2fa.enroll', ['user_id' => $userId]);

        return Response::json([
            'secret' => $secret,
            'otpauth_uri' => $otpauth,
            'qr_data_uri' => $qr,
        ]);
    }

    #[Path(path: '/api/v1/auth/2fa/enroll/confirm', method: 'POST')]
    #[RequireAuth]
    #[OA\Post(
        path: '/api/v1/auth/2fa/enroll/confirm',
        summary: 'Activate a pending TOTP enrollment by submitting the first valid code.',
        tags: ['auth'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ConfirmTwoFactorRequest')),
        responses: [
            new OA\Response(response: 200, description: '2FA enabled.'),
            new OA\Response(response: 400, description: 'Invalid TOTP code.'),
            new OA\Response(response: 401, description: 'Missing or invalid Bearer token.'),
            new OA\Response(response: 409, description: 'Enrollment not started or already active.'),
            new OA\Response(response: 422, description: 'Body failed validation.'),
        ],
    )]
    public function confirmTwoFactor(ConfirmTwoFactorRequest $body): Response
    {
        $authUser = $this->request->authUser;
        if (!$authUser instanceof AuthenticatedUser) {
            return $this->problem(401, 'Not authenticated.');
        }
        $userId = (int) $authUser->id;

        $row = $this->twoFactorRepo->findByUserIdAndMethod($userId, TwoFactorRepository::METHOD_APP);
        if ($row === null) {
            return $this->problem(409, 'Enrollment not started.', 'enrollment_not_started');
        }
        if ((int) ($row['enabled'] ?? 0) === 1) {
            return $this->problem(409, '2FA already enabled.', '2fa_already_enabled');
        }

        $secret = (string) ($row['secret_key'] ?? '');
        if ($secret === '' || !($this->totpVerifier)($secret, $body->code)) {
            $this->logger->info('auth.2fa.confirm.fail', ['user_id' => $userId]);
            return $this->problem(400, 'Invalid 2FA code.', 'invalid_code');
        }

        $this->twoFactorRepo->setEnabled($userId, TwoFactorRepository::METHOD_APP, true);
        $this->logger->info('auth.2fa.confirm', ['user_id' => $userId]);

        return Response::json(['enabled' => true, 'method' => TwoFactorRepository::METHOD_APP]);
    }

    #[Path(path: '/api/v1/auth/2fa/disable', method: 'POST')]
    #[RequireAuth]
    #[OA\Post(
        path: '/api/v1/auth/2fa/disable',
        summary: 'Disable an active TOTP enrollment. Requires a fresh password to defeat hijacked-session abuse.',
        tags: ['auth'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DisableTwoFactorRequest')),
        responses: [
            new OA\Response(response: 200, description: '2FA disabled.'),
            new OA\Response(response: 401, description: 'Missing Bearer token or password mismatch.'),
            new OA\Response(response: 422, description: 'Body failed validation.'),
        ],
    )]
    public function disableTwoFactor(DisableTwoFactorRequest $body): Response
    {
        $authUser = $this->request->authUser;
        if (!$authUser instanceof AuthenticatedUser) {
            return $this->problem(401, 'Not authenticated.');
        }
        $userId = (int) $authUser->id;

        $user = User::findByID($userId);
        if (!$user instanceof AbstractUser || !is_string($user->password_hash ?? null)) {
            return $this->problem(401, 'User not found.', 'user_missing');
        }
        if (!password_verify($body->password, (string) $user->password_hash)) {
            return $this->problem(401, 'Password verification failed.', 'password_required');
        }

        $this->twoFactorRepo->setEnabled($userId, TwoFactorRepository::METHOD_APP, false);
        $this->logger->info('auth.2fa.disable', ['user_id' => $userId]);

        return Response::json(['enabled' => false]);
    }

    private function issueSession(AbstractUser $user): Response
    {
        $userId = (int) $user->id;
        $roles = $user->getRoles();
        $access = $this->tokenService->issueAccessToken($userId, $roles);
        $refresh = $this->tokenService->issueRefreshToken(
            userId: $userId,
            userAgent: $this->request->server['HTTP_USER_AGENT'] ?? null,
            ip: $this->request->server['REMOTE_ADDR'] ?? null,
        );

        $this->setRefreshCookie($refresh['token']);
        $csrf = $this->rotateCsrfCookie();

        return Response::json([
            'access_token' => $access,
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTtl,
            'csrf_token' => $csrf,
            'user' => [
                'id' => $userId,
                'email' => $user->email ?? null,
                'username' => $user->username ?? null,
                'roles' => $roles,
            ],
        ]);
    }

    private function problem(int $status, string $detail, ?string $code = null): Response
    {
        $payload = [
            'type' => 'about:blank',
            'title' => match ($status) {
                400 => 'Bad Request',
                401 => 'Unauthorized',
                403 => 'Forbidden',
                404 => 'Not Found',
                409 => 'Conflict',
                default => 'Error',
            },
            'status' => $status,
            'detail' => $detail,
        ];
        if ($code !== null) {
            $payload['code'] = $code;
        }
        $response = Response::json($payload, $status);
        $response->addHeader('Content-Type: application/problem+json; charset=utf-8');
        return $response;
    }

    private function setRefreshCookie(string $token): void
    {
        $this->response->addHeader($this->buildCookie(
            name: self::REFRESH_COOKIE,
            value: $token,
            maxAge: $this->refreshTtl,
            path: self::REFRESH_COOKIE_PATH,
            httpOnly: true,
            sameSite: 'Strict',
        ));
    }

    private function clearRefreshCookie(): void
    {
        $this->response->addHeader($this->buildCookie(
            name: self::REFRESH_COOKIE,
            value: '',
            maxAge: 0,
            path: self::REFRESH_COOKIE_PATH,
            httpOnly: true,
            sameSite: 'Strict',
        ));
    }

    private function rotateCsrfCookie(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->response->addHeader($this->buildCookie(
            name: self::CSRF_COOKIE,
            value: $token,
            maxAge: $this->refreshTtl,
            path: '/',
            httpOnly: false,
            sameSite: 'Strict',
        ));
        return $token;
    }

    private function clearCsrfCookie(): void
    {
        $this->response->addHeader($this->buildCookie(
            name: self::CSRF_COOKIE,
            value: '',
            maxAge: 0,
            path: '/',
            httpOnly: false,
            sameSite: 'Strict',
        ));
    }

    private function buildCookie(
        string $name,
        string $value,
        int $maxAge,
        string $path,
        bool $httpOnly,
        string $sameSite,
    ): string {
        $parts = [
            sprintf('%s=%s', $name, $value),
            sprintf('Max-Age=%d', $maxAge),
            sprintf('Path=%s', $path),
            sprintf('SameSite=%s', $sameSite),
        ];
        if ($this->isSecureContext()) {
            $parts[] = 'Secure';
        }
        if ($httpOnly) {
            $parts[] = 'HttpOnly';
        }
        return 'Set-Cookie: ' . implode('; ', $parts);
    }

    /**
     * Resolve the Secure cookie attribute per-request: true if the current
     * request is HTTPS (direct or via trusted X-Forwarded-Proto), or if the
     * deployment forces HTTPS via APP_FORCE_HTTPS — in which case the very
     * next request will hit HTTPS regardless of what we see on this hop.
     *
     * The `__Host-` cookie prefix also *requires* Secure; getting this right
     * is what makes the refresh cookie survive the upstream proxy.
     */
    private function isSecureContext(): bool
    {
        if (RequestScheme::forceHttpsFromEnv()) {
            return true;
        }
        return RequestScheme::isHttpsFromServerParams(
            $this->request->server,
            RequestScheme::trustProxyFromEnv(),
        );
    }
}
