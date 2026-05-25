<?php

declare(strict_types=1);

namespace Framework\Controllers\Api\V1;

use Application\Dto\LoginRequest;
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
use Framework\Models\AbstractUser;
use Framework\Models\TwoFactorModel;
use Framework\Models\User;
use Framework\Path;
use Framework\Response;
use Framework\TwoFactor;
use OpenApi\Attributes as OA;

/**
 * JWT-alapú auth endpointok. Az M2.b állapotában:
 *
 *   POST   /api/v1/auth/login       — email + password → access + refresh,
 *                                     vagy 2FA challenge token, ha a usernek
 *                                     engedélyezett TOTP-je van.
 *   POST   /api/v1/auth/2fa/verify  — challenge_token + code → access + refresh.
 *   POST   /api/v1/auth/refresh     — rotated access + refresh (double-submit CSRF).
 *   POST   /api/v1/auth/logout      — revoke + cookie clear.
 *   GET    /api/v1/auth/me          — aktuális user (RequireAuth).
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
    private int $accessTtl;
    private int $refreshTtl;
    private int $challengeTtl;
    private bool $secureCookies;

    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->boot();
    }

    /** Tesztből / containerből cserélhető. */
    public function setTokenService(TokenService $service, int $accessTtl, int $refreshTtl, bool $secureCookies): void
    {
        $this->tokenService = $service;
        $this->accessTtl = $accessTtl;
        $this->refreshTtl = $refreshTtl;
        $this->secureCookies = $secureCookies;
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

    private function boot(): void
    {
        $config = require dirname(__DIR__, 4) . '/config/jwt.php';
        $this->accessTtl = (int) $config['access_ttl'];
        $this->refreshTtl = (int) $config['refresh_ttl'];
        $this->challengeTtl = (int) ($config['challenge_ttl'] ?? 300);
        $this->secureCookies = (getenv('APP_ENV') ?: 'production') !== 'local';
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
        $user = User::authenticate($body->email, $body->password);
        if ($user === false) {
            return $this->problem(401, 'Invalid credentials.');
        }

        $methods = $this->enabledTwoFactorMethods((int) $user->id);
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

        $twoFactor = TwoFactorModel::findByUserIdAndMethod($userId, TwoFactorModel::METHOD_APP);
        if (!$twoFactor instanceof TwoFactorModel || (int) $twoFactor->enabled !== (int) Dal::TRUE) {
            return $this->problem(401, '2FA is not enabled for this user.');
        }

        $secret = (string) ($twoFactor->secret_key ?? '');
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
        ]);
    }

    /**
     * @return list<string> az engedélyezett 2FA módszerek (pl. "app").
     */
    private function enabledTwoFactorMethods(int $userId): array
    {
        $rows = TwoFactorModel::findByUserId($userId);
        if (!is_array($rows) || $rows === []) {
            return [];
        }
        $methods = [];
        foreach ($rows as $row) {
            if ((int) ($row->enabled ?? 0) === (int) Dal::TRUE) {
                $methods[] = (string) $row->method;
            }
        }
        return array_values(array_unique($methods));
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

    private function problem(int $status, string $detail): Response
    {
        $response = Response::json([
            'type' => 'about:blank',
            'title' => match ($status) {
                400 => 'Bad Request',
                401 => 'Unauthorized',
                403 => 'Forbidden',
                404 => 'Not Found',
                default => 'Error',
            },
            'status' => $status,
            'detail' => $detail,
        ], $status);
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
        if ($this->secureCookies) {
            $parts[] = 'Secure';
        }
        if ($httpOnly) {
            $parts[] = 'HttpOnly';
        }
        return 'Set-Cookie: ' . implode('; ', $parts);
    }
}
