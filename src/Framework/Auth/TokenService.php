<?php

declare(strict_types=1);

namespace Framework\Auth;

use DateInterval;
use DateTimeImmutable;
use DomainException;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Psr\Clock\ClockInterface;
use RuntimeException;

/**
 * Access és refresh tokenek kiállítása, ellenőrzése és rotálása.
 *
 * Access token: RS256-tal aláírt JWT, rövid (default 15 perc) TTL-lel.
 * Refresh token: random 256-bites string, hash-elve tárolva a
 * `refresh_tokens` táblában. Rotáció: minden /refresh hívás revokálja
 * a régit és újat ad. Ha egy már revokált tokent használnak újra
 * (reuse), a teljes család (family_id) revokálódik — feltehetően lopás.
 */
final class TokenService
{
    public function __construct(
        private readonly Configuration $jwt,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly ClockInterface $clock,
        private readonly string $issuer,
        private readonly string $audience,
        private readonly int $accessTtl,
        private readonly int $refreshTtl,
        private readonly int $clockSkew = 5,
    ) {
    }

    /**
     * @param list<string> $roles
     */
    public function issueAccessToken(int $userId, array $roles = []): string
    {
        $now = $this->clock->now();
        $token = $this->jwt->builder()
            ->issuedBy($this->issuer)
            ->permittedFor($this->audience)
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->issuedAt($now)
            ->expiresAt($now->add(new DateInterval('PT' . $this->accessTtl . 'S')))
            ->relatedTo((string) $userId)
            ->withClaim('roles', array_values($roles))
            ->getToken($this->jwt->signer(), $this->jwt->signingKey());

        return $token->toString();
    }

    public function verifyAccess(string $jwt): Plain
    {
        try {
            $token = $this->jwt->parser()->parse($jwt);
        } catch (\Throwable $e) {
            throw new DomainException('Malformed access token.', 401, $e);
        }
        if (!$token instanceof Plain) {
            throw new DomainException('Unsupported token format.', 401);
        }

        try {
            $this->jwt->validator()->assert(
                $token,
                new SignedWith($this->jwt->signer(), $this->jwt->verificationKey()),
                new LooseValidAt($this->clock, new DateInterval('PT' . $this->clockSkew . 'S')),
                new IssuedBy($this->issuer),
                new PermittedFor($this->audience),
            );
        } catch (RequiredConstraintsViolated $e) {
            throw new DomainException('Invalid access token: ' . $e->getMessage(), 401, $e);
        }

        return $token;
    }

    /**
     * Új refresh token család gyökerét adja vissza, mellette eltárolja a
     * hash-t. A visszaadott string a plain token — ezt küldjük cookie-ban.
     *
     * @return array{token: string, family_id: string, expires_at: DateTimeImmutable}
     */
    public function issueRefreshToken(int $userId, ?string $userAgent = null, ?string $ip = null): array
    {
        $now = $this->clock->now();
        $expiresAt = $now->add(new DateInterval('PT' . $this->refreshTtl . 'S'));
        $token = $this->generateRefreshToken();
        $familyId = bin2hex(random_bytes(16));

        $this->refreshTokens->store(
            userId: $userId,
            familyId: $familyId,
            tokenHash: $this->hashRefresh($token),
            rotatedFrom: null,
            expiresAt: $expiresAt,
            userAgent: $userAgent,
            ip: $ip,
        );

        return ['token' => $token, 'family_id' => $familyId, 'expires_at' => $expiresAt];
    }

    /**
     * A kliens által küldött refresh tokent verifikálja, revokálja, és új
     * access+refresh tokent ad. Ha a régi token már revokált, az egész
     * család revokálódik (reuse detection).
     *
     * @param list<string> $roles
     * @return array{access_token: string, refresh_token: string, expires_at: DateTimeImmutable}
     */
    public function rotateRefresh(string $refreshToken, int $userId, array $roles, ?string $userAgent = null, ?string $ip = null): array
    {
        $hash = $this->hashRefresh($refreshToken);
        $existing = $this->refreshTokens->findByHash($hash);

        if ($existing === null) {
            throw new DomainException('Unknown refresh token.', 401);
        }
        if ((int) $existing['user_id'] !== $userId) {
            throw new DomainException('Refresh token does not belong to user.', 401);
        }
        if ($existing['revoked_at'] !== null) {
            // Reuse detected — kioltjuk a teljes családot.
            $this->refreshTokens->revokeFamily($existing['family_id']);
            throw new DomainException('Refresh token reuse detected; family revoked.', 401);
        }
        $now = $this->clock->now();
        $expiresAt = new DateTimeImmutable($existing['expires_at']);
        if ($expiresAt <= $now) {
            throw new DomainException('Refresh token expired.', 401);
        }

        $newToken = $this->generateRefreshToken();
        $newExpiresAt = $now->add(new DateInterval('PT' . $this->refreshTtl . 'S'));

        $this->refreshTokens->markRotated((int) $existing['id'], $now);
        $this->refreshTokens->store(
            userId: $userId,
            familyId: (string) $existing['family_id'],
            tokenHash: $this->hashRefresh($newToken),
            rotatedFrom: (int) $existing['id'],
            expiresAt: $newExpiresAt,
            userAgent: $userAgent,
            ip: $ip,
        );

        return [
            'access_token' => $this->issueAccessToken($userId, $roles),
            'refresh_token' => $newToken,
            'expires_at' => $newExpiresAt,
        ];
    }

    public function revokeRefresh(string $refreshToken): void
    {
        $existing = $this->refreshTokens->findByHash($this->hashRefresh($refreshToken));
        if ($existing === null || $existing['revoked_at'] !== null) {
            return;
        }
        $this->refreshTokens->markRotated((int) $existing['id'], $this->clock->now());
    }

    private function generateRefreshToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    private function hashRefresh(string $token): string
    {
        return hash('sha256', $token);
    }
}
