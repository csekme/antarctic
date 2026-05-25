<?php

declare(strict_types=1);

namespace Framework\Auth;

use DateInterval;
use DomainException;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Psr\Clock\ClockInterface;

/**
 * Két-lépcsős login köztes tokenje. A jelszó-ellenőrzés sikere után a
 * szerver kiállít egy rövid életű JWT-t `purpose: 2fa_challenge` claim-mel.
 * A kliens ezt küldi vissza a `/api/v1/auth/2fa/verify` endpointra a TOTP
 * kóddal együtt. Sikeres TOTP után indul a normál access + refresh issue.
 *
 * Biztonsági jegyzetek:
 *  - A challenge token önmagában nem hatalmaz fel semmilyen erőforrás-hozzáférésre.
 *  - A `purpose` claim szigorúan ellenőrzött; egy access tokent nem lehet
 *    challenge-ként használni és fordítva.
 *  - TTL alapértelmezetten 300s; a TOTP saját 30s-os ablaka adja a tényleges
 *    reuse-védelmet, de a rövid TTL így is csökkenti a támadási felületet.
 */
final class TwoFactorChallengeService
{
    public const PURPOSE = '2fa_challenge';

    public function __construct(
        private readonly Configuration $jwt,
        private readonly ClockInterface $clock,
        private readonly string $issuer,
        private readonly string $audience,
        private readonly int $ttl,
        private readonly int $clockSkew = 5,
    ) {
    }

    public function issueChallenge(int $userId): string
    {
        $now = $this->clock->now();
        $token = $this->jwt->builder()
            ->issuedBy($this->issuer)
            ->permittedFor($this->audience)
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->issuedAt($now)
            ->expiresAt($now->add(new DateInterval('PT' . $this->ttl . 'S')))
            ->relatedTo((string) $userId)
            ->withClaim('purpose', self::PURPOSE)
            ->getToken($this->jwt->signer(), $this->jwt->signingKey());

        return $token->toString();
    }

    /**
     * @return int A user ID, amelyre a challenge szólt.
     * @throws DomainException ha a token érvénytelen, lejárt, vagy nem 2FA challenge.
     */
    public function verifyChallenge(string $jwt): int
    {
        try {
            $token = $this->jwt->parser()->parse($jwt);
        } catch (\Throwable $e) {
            throw new DomainException('Malformed challenge token.', 401, $e);
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
            throw new DomainException('Invalid challenge token: ' . $e->getMessage(), 401, $e);
        }

        $claims = $token->claims();
        $purpose = $claims->has('purpose') ? (string) $claims->get('purpose') : '';
        if (!hash_equals(self::PURPOSE, $purpose)) {
            throw new DomainException('Token is not a 2FA challenge.', 401);
        }

        $sub = $claims->has('sub') ? (string) $claims->get('sub') : '';
        if ($sub === '' || !ctype_digit($sub)) {
            throw new DomainException('Challenge token missing subject.', 401);
        }

        return (int) $sub;
    }

    public function ttl(): int
    {
        return $this->ttl;
    }
}
