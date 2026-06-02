<?php

declare(strict_types=1);

namespace Framework\Auth;

use DomainException;
use Framework\Dal;
use Framework\Models\AbstractUser;
use Framework\Models\User;

/**
 * Validates the refresh cookie + CSRF double-submit pair and rotates the
 * refresh token. Cookie/header values come in as parameters — the service
 * never touches `$_COOKIE` or `$_SERVER` directly, which makes testing
 * straightforward (no superglobal fixture).
 */
final class RefreshSessionService
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly int $accessTtl,
    ) {
    }

    public function rotate(
        ?string $refreshCookie,
        ?string $csrfCookie,
        ?string $csrfHeader,
        ?string $userAgent,
        ?string $ip,
    ): RefreshSessionResult {
        if (!is_string($refreshCookie) || $refreshCookie === '') {
            return new RefreshSessionResult(RefreshSessionStatus::MissingCookie);
        }
        if (
            !is_string($csrfCookie) || !is_string($csrfHeader)
            || $csrfHeader === '' || !hash_equals($csrfCookie, $csrfHeader)
        ) {
            return new RefreshSessionResult(RefreshSessionStatus::CsrfMismatch);
        }

        $hash = hash('sha256', $refreshCookie);
        $existing = (new RefreshTokenRepository(Dal::getConnection()))->findByHash($hash);
        if ($existing === null) {
            return new RefreshSessionResult(RefreshSessionStatus::TokenUnknown);
        }

        $userId = (int) $existing['user_id'];
        $user = User::findByID($userId);
        if (!$user instanceof AbstractUser || !($user->is_active ?? true)) {
            return new RefreshSessionResult(RefreshSessionStatus::UserInactive);
        }

        try {
            $rotated = $this->tokenService->rotateRefresh(
                refreshToken: $refreshCookie,
                userId: $userId,
                roles: $user->getRoles(),
                userAgent: $userAgent,
                ip: $ip,
            );
        } catch (DomainException $e) {
            // TokenService throws on reuse-detection (revokes the family) or
            // on plain expiry / signature mismatch — we map both to a single
            // "must re-login" status, the controller returns 401.
            return new RefreshSessionResult(RefreshSessionStatus::RotationFailed, reason: $e->getMessage());
        }

        return new RefreshSessionResult(
            RefreshSessionStatus::Ok,
            accessToken: $rotated['access_token'],
            refreshToken: $rotated['refresh_token'],
            accessTtl: $this->accessTtl,
        );
    }
}
