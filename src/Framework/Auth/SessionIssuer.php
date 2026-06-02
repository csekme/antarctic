<?php

declare(strict_types=1);

namespace Framework\Auth;

use Framework\Models\AbstractUser;

/**
 * Issues an access + refresh token pair for a freshly-authenticated user.
 * Wraps TokenService so the controller does not have to know about the
 * two-call sequence (access + refresh) and the user-agent / ip plumbing.
 *
 * Three callers: LoginService → success path, TwoFactorLoginService →
 * verified-code path, and (future) RegistrationService if we ever decide
 * to auto-login after register.
 */
final class SessionIssuer
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly int $accessTtl,
        private readonly int $refreshTtl,
    ) {
    }

    public function issue(AbstractUser $user, ?string $userAgent, ?string $ip): IssuedSession
    {
        $userId = (int) $user->id;
        $roles = $user->getRoles();
        $access = $this->tokenService->issueAccessToken($userId, $roles);
        $refresh = $this->tokenService->issueRefreshToken(
            userId: $userId,
            userAgent: $userAgent,
            ip: $ip,
        );

        return new IssuedSession(
            accessToken: $access,
            refreshToken: $refresh['token'],
            accessTtl: $this->accessTtl,
            refreshTtl: $this->refreshTtl,
            roles: $roles,
        );
    }
}
