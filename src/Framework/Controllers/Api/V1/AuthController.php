<?php

declare(strict_types=1);

namespace Framework\Controllers\Api\V1;

use Application\Dto\ConfirmTwoFactorRequest;
use Application\Dto\DisableTwoFactorRequest;
use Application\Dto\LoginRequest;
use Application\Dto\RegisterRequest;
use Application\Dto\VerifyEmailRequest;
use Application\Dto\VerifyTwoFactorRequest;
use Framework\Auth\AuthenticatedUser;
use Framework\Auth\ConfirmStatus;
use Framework\Auth\DisableStatus;
use Framework\Auth\EmailVerificationService;
use Framework\Auth\EnrollStatus;
use Framework\Auth\JwtConfigFactory;
use Framework\Auth\LoginService;
use Framework\Auth\LoginStatus;
use Framework\Auth\RefreshCookieJar;
use Framework\Auth\RefreshSessionResult;
use Framework\Auth\RefreshSessionService;
use Framework\Auth\RefreshSessionStatus;
use Framework\Auth\RefreshTokenRepository;
use Framework\Auth\RegistrationResult;
use Framework\Auth\RegistrationService;
use Framework\Auth\RegistrationStatus;
use Framework\Auth\RequireAuth;
use Framework\Auth\SessionIssuer;
use Framework\Auth\SystemClock;
use Framework\Auth\TokenService;
use Framework\Auth\TwoFactorChallengeService;
use Framework\Auth\TwoFactorLoginService;
use Framework\Auth\TwoFactorLoginStatus;
use Framework\Auth\TwoFactorSetupService;
use Framework\Auth\VerifyStatus;
use Framework\Controller;
use Framework\Dal;
use Framework\Logging\LoggerFactory;
use Framework\Models\AbstractUser;
use Framework\Models\User;
use Framework\Path;
use Framework\Repositories\TwoFactorRepository;
use Framework\Response;
use OpenApi\Attributes as OA;

/**
 * JWT-alapú auth endpointok. A controller egy vékony HTTP-határ:
 *
 *   DTO → service (use-case) → Result enum + payload → HTTP/cookie kimenet.
 *
 * A use-case logika a Framework\Auth\ alatt él (LoginService, RegistrationService,
 * EmailVerificationService, TwoFactorLoginService, TwoFactorSetupService,
 * RefreshSessionService, SessionIssuer). A controller csak az enum-match-elt
 * leképezést és a cookie-rakást végzi a RefreshCookieJar segítségével.
 */
class AuthController extends Controller
{
    private LoginService $loginService;
    private TwoFactorLoginService $twoFactorLoginService;
    private RefreshSessionService $refreshSessionService;
    private RegistrationService $registrationService;
    private EmailVerificationService $emailVerificationService;
    private TwoFactorSetupService $twoFactorSetupService;
    private SessionIssuer $sessionIssuer;
    private RefreshCookieJar $cookieJar;
    private TwoFactorRepository $twoFactorRepo;
    private TokenService $tokenService;
    private TwoFactorChallengeService $challengeService;
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

    /* ----------------------------------------------------------------------
     * Setters
     * --------------------------------------------------------------------*/

    public function setLoginService(LoginService $s): void
    {
        $this->loginService = $s;
    }

    public function setTwoFactorLoginService(TwoFactorLoginService $s): void
    {
        $this->twoFactorLoginService = $s;
    }

    public function setRefreshSessionService(RefreshSessionService $s): void
    {
        $this->refreshSessionService = $s;
    }

    public function setRegistrationService(RegistrationService $s): void
    {
        $this->registrationService = $s;
    }

    public function setEmailVerificationService(EmailVerificationService $s): void
    {
        $this->emailVerificationService = $s;
    }

    public function setTwoFactorSetupService(TwoFactorSetupService $s): void
    {
        $this->twoFactorSetupService = $s;
    }

    public function setSessionIssuer(SessionIssuer $s): void
    {
        $this->sessionIssuer = $s;
    }

    public function setCookieJar(RefreshCookieJar $jar): void
    {
        $this->cookieJar = $jar;
    }

    /* Legacy delegating setters — kept for backwards-compat with existing
     * tests that injected a spy mailer / verifier / enroller on the
     * controller directly. They now forward to the service that owns the
     * dependency. */

    /** @param callable(string, string, string): (bool|string) $mailer */
    public function setMailer(callable $mailer): void
    {
        $this->registrationService->setMailer($mailer);
    }

    /** @param callable(string, string): bool $verifier */
    public function setTotpVerifier(callable $verifier): void
    {
        $this->twoFactorLoginService->setTotpVerifier($verifier);
        $this->twoFactorSetupService->setTotpVerifier($verifier);
    }

    /** @param callable(): array{secret: string, qr_data_uri: string} $enroller */
    public function setTwoFactorEnroller(callable $enroller): void
    {
        $this->twoFactorSetupService->setEnroller($enroller);
    }

    /* ----------------------------------------------------------------------
     * Boot — wire up the service graph with sane defaults.
     * --------------------------------------------------------------------*/

    private function boot(): void
    {
        $config = require dirname(__DIR__, 4) . '/config/jwt.php';
        $this->accessTtl = (int) $config['access_ttl'];
        $this->refreshTtl = (int) $config['refresh_ttl'];
        $this->challengeTtl = (int) ($config['challenge_ttl'] ?? 300);

        $jwtConfig = JwtConfigFactory::create($config);
        $clock = new SystemClock();
        $logger = LoggerFactory::fromEnv();

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
        $this->twoFactorRepo = new TwoFactorRepository(Dal::getConnection());

        $this->loginService = new LoginService($this->twoFactorRepo);
        $this->twoFactorLoginService = new TwoFactorLoginService($this->challengeService, $this->twoFactorRepo);
        $this->refreshSessionService = new RefreshSessionService($this->tokenService, $this->accessTtl);
        $this->registrationService = new RegistrationService($logger);
        $this->emailVerificationService = new EmailVerificationService($logger);
        $this->twoFactorSetupService = new TwoFactorSetupService($this->twoFactorRepo, $logger);
        $this->sessionIssuer = new SessionIssuer($this->tokenService, $this->accessTtl, $this->refreshTtl);
        $this->cookieJar = RefreshCookieJar::fromServerParams($this->request->server);
    }

    /* ----------------------------------------------------------------------
     * Endpoints
     * --------------------------------------------------------------------*/

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
            new OA\Response(response: 403, description: 'Email not verified.'),
            new OA\Response(response: 422, description: 'Body failed validation.'),
        ],
    )]
    public function login(LoginRequest $body): Response
    {
        $result = $this->loginService->attempt($body->email, $body->password);
        return match ($result->status) {
            LoginStatus::Ok => $this->withSession($this->requireUser($result->user)),
            LoginStatus::TwoFactorRequired => $this->twoFactorChallenge($this->requireUser($result->user), $result->methods),
            LoginStatus::Unverified => $this->problem(403, 'Email not verified.', 'email_not_verified'),
            LoginStatus::InvalidCredentials => $this->problem(401, 'Invalid credentials.'),
        };
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
        $result = $this->twoFactorLoginService->verify($body->challenge_token, $body->code);
        return match ($result->status) {
            TwoFactorLoginStatus::Ok => $this->withSession($this->requireUser($result->user)),
            TwoFactorLoginStatus::ChallengeInvalid => $this->problem(401, $result->reason ?? 'Invalid challenge.'),
            TwoFactorLoginStatus::UserInactive => $this->problem(401, 'User is inactive.'),
            TwoFactorLoginStatus::NotEnabled => $this->problem(401, '2FA is not enabled for this user.'),
            TwoFactorLoginStatus::InvalidCode => $this->problem(401, 'Invalid 2FA code.'),
        };
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
        $result = $this->refreshSessionService->rotate(
            refreshCookie: $_COOKIE[$this->cookieJar->refreshCookieName()] ?? null,
            csrfCookie: $_COOKIE[RefreshCookieJar::CSRF_COOKIE] ?? null,
            csrfHeader: $this->request->server['HTTP_X_CSRF_TOKEN'] ?? null,
            userAgent: $this->request->server['HTTP_USER_AGENT'] ?? null,
            ip: $this->request->server['REMOTE_ADDR'] ?? null,
        );
        return match ($result->status) {
            RefreshSessionStatus::Ok => $this->emitRotatedSession($result),
            RefreshSessionStatus::MissingCookie => $this->problem(401, 'Refresh cookie missing.'),
            RefreshSessionStatus::CsrfMismatch => $this->problem(403, 'CSRF token mismatch.'),
            RefreshSessionStatus::TokenUnknown => $this->problem(401, 'Unknown refresh token.'),
            RefreshSessionStatus::UserInactive => $this->problem(401, 'User is inactive.'),
            RefreshSessionStatus::RotationFailed => $this->problem(401, $result->reason ?? 'Refresh failed.'),
        };
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
        $refresh = $_COOKIE[$this->cookieJar->refreshCookieName()] ?? null;
        if (is_string($refresh) && $refresh !== '') {
            $this->tokenService->revokeRefresh($refresh);
        }
        $response = Response::json(['ok' => true]);
        $response->addHeader($this->cookieJar->buildRefreshClearCookie());
        $response->addHeader($this->cookieJar->buildCsrfClearCookie());
        return $response;
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
        $authUser = $this->request->authUser;
        if (!$authUser instanceof AuthenticatedUser) {
            return $this->problem(401, 'Not authenticated.');
        }
        $entity = User::findByID($authUser->id);
        if ($entity === false) {
            return $this->problem(404, 'User not found.');
        }

        return Response::json([
            'id' => $authUser->id,
            'email' => $entity->email ?? null,
            'username' => $entity->username ?? null,
            'roles' => $authUser->roles,
            'two_factor' => [
                'methods' => $this->twoFactorRepo->enabledMethods((int) $authUser->id),
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
        $context = [
            'ip' => $this->request->server['REMOTE_ADDR'] ?? null,
            'ua' => $this->request->server['HTTP_USER_AGENT'] ?? null,
        ];
        $result = $this->registrationService->register($body, $context);
        return match ($result->status) {
            RegistrationStatus::Created => $this->emitRegistrationSuccess($body, $result),
            RegistrationStatus::EmailTaken => $this->problem(409, 'Email already registered.', 'email_already_registered'),
            RegistrationStatus::UsernameTaken => $this->problem(409, 'Username already taken.', 'username_taken'),
            RegistrationStatus::Failed => $this->problem(500, 'Could not create user.', 'register_failed'),
        };
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
        $result = $this->emailVerificationService->verify($body->token);
        return match ($result->status) {
            VerifyStatus::Ok => Response::json(['verified' => true]),
            VerifyStatus::Unknown => $this->problem(404, 'Token unknown or expired.', 'token_unknown_or_expired'),
            VerifyStatus::Misconfigured => $this->problem(500, 'Server misconfiguration.', 'verify_misconfigured'),
        };
    }

    #[Path(path: '/api/v1/auth/2fa/enroll', method: 'POST')]
    #[RequireAuth]
    #[OA\Post(
        path: '/api/v1/auth/2fa/enroll',
        summary: 'Start TOTP enrollment for the current user.',
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
        $userEntity = User::findByID($userId);
        $email = $userEntity instanceof AbstractUser ? (string) ($userEntity->email ?? '') : '';

        $result = $this->twoFactorSetupService->enroll($userId, $email);
        return match ($result->status) {
            EnrollStatus::Started => Response::json([
                'secret' => $result->secret,
                'otpauth_uri' => $result->otpauthUri,
                'qr_data_uri' => $result->qrDataUri,
            ]),
            EnrollStatus::AlreadyEnabled => $this->problem(409, '2FA already enabled.', '2fa_already_enabled'),
            EnrollStatus::Failed => $this->problem(500, 'Could not generate 2FA secret.', '2fa_enroll_failed'),
        };
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
        $result = $this->twoFactorSetupService->confirm((int) $authUser->id, $body->code);
        return match ($result->status) {
            ConfirmStatus::Enabled => Response::json(['enabled' => true, 'method' => TwoFactorRepository::METHOD_APP]),
            ConfirmStatus::NotStarted => $this->problem(409, 'Enrollment not started.', 'enrollment_not_started'),
            ConfirmStatus::AlreadyEnabled => $this->problem(409, '2FA already enabled.', '2fa_already_enabled'),
            ConfirmStatus::InvalidCode => $this->problem(400, 'Invalid 2FA code.', 'invalid_code'),
        };
    }

    #[Path(path: '/api/v1/auth/2fa/disable', method: 'POST')]
    #[RequireAuth]
    #[OA\Post(
        path: '/api/v1/auth/2fa/disable',
        summary: 'Disable an active TOTP enrollment with a password re-auth.',
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
        $result = $this->twoFactorSetupService->disable((int) $authUser->id, $body->password);
        return match ($result->status) {
            DisableStatus::Disabled => Response::json(['enabled' => false]),
            DisableStatus::WrongPassword => $this->problem(401, 'Password verification failed.', 'password_required'),
            DisableStatus::UserMissing => $this->problem(401, 'User not found.', 'user_missing'),
        };
    }

    /* ----------------------------------------------------------------------
     * Private response helpers
     * --------------------------------------------------------------------*/

    private function withSession(AbstractUser $user): Response
    {
        $session = $this->sessionIssuer->issue(
            $user,
            $this->request->server['HTTP_USER_AGENT'] ?? null,
            $this->request->server['REMOTE_ADDR'] ?? null,
        );
        $response = Response::json(['placeholder' => true]);
        $response->addHeader($this->cookieJar->buildRefreshSetCookie($session->refreshToken, $session->refreshTtl));
        $csrf = RefreshCookieJar::generateCsrfToken();
        $response->addHeader($this->cookieJar->buildCsrfSetCookie($csrf, $session->refreshTtl));
        $response->setBody((string) json_encode([
            'access_token' => $session->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $session->accessTtl,
            'csrf_token' => $csrf,
            'user' => [
                'id' => (int) $user->id,
                'email' => $user->email ?? null,
                'username' => $user->username ?? null,
                'roles' => $session->roles,
            ],
        ]));
        return $response;
    }

    /**
     * @param list<string> $methods
     */
    private function twoFactorChallenge(AbstractUser $user, array $methods): Response
    {
        $challenge = $this->challengeService->issueChallenge((int) $user->id);
        return Response::json([
            'requires' => '2fa',
            'challenge_token' => $challenge,
            'methods' => $methods,
            'expires_in' => $this->challengeTtl,
        ]);
    }

    private function emitRotatedSession(RefreshSessionResult $result): Response
    {
        $response = Response::json(['placeholder' => true]);
        $response->addHeader($this->cookieJar->buildRefreshSetCookie((string) $result->refreshToken, $this->refreshTtl));
        $csrf = RefreshCookieJar::generateCsrfToken();
        $response->addHeader($this->cookieJar->buildCsrfSetCookie($csrf, $this->refreshTtl));
        $response->setBody((string) json_encode([
            'access_token' => $result->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $result->accessTtl,
            'csrf_token' => $csrf,
        ]));
        return $response;
    }

    private function emitRegistrationSuccess(RegisterRequest $body, RegistrationResult $result): Response
    {
        $payload = [
            'user' => [
                'id' => $result->userId,
                'email' => $body->email,
                'username' => $body->username,
            ],
            'requires_verification' => true,
        ];
        if ($result->verificationLink !== null) {
            $payload['verification_link'] = $result->verificationLink;
        }
        return Response::json($payload, 201);
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

    /**
     * Narrows the optional User payload from a Result enum to a concrete
     * AbstractUser for the type-system. The status branches that carry no
     * user (InvalidCredentials, Unverified, …) never reach this helper, so
     * a null value here is a programming error.
     */
    private function requireUser(?AbstractUser $user): AbstractUser
    {
        if (!$user instanceof AbstractUser) {
            throw new \LogicException('Result said Ok/2FA but no user was carried.');
        }
        return $user;
    }
}
