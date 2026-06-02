<?php

declare(strict_types=1);

namespace Framework\Auth;

use DomainException;
use Framework\Models\User;
use Framework\Repositories\TwoFactorRepository;

/**
 * Step two of the login flow when a user has 2FA enabled: decode the
 * short-lived challenge JWT issued by LoginService, validate the TOTP
 * code, and return a user ready for SessionIssuer.
 */
final class TwoFactorLoginService
{
    /** @var callable(string $secret, string $code): bool */
    private $totpVerifier;

    public function __construct(
        private readonly TwoFactorChallengeService $challengeService,
        private readonly TwoFactorRepository $twoFactorRepo,
        ?callable $totpVerifier = null,
    ) {
        $this->totpVerifier = $totpVerifier ?? static function (string $secret, string $code): bool {
            return (new \Framework\TwoFactor())->verifyCode($secret, $code);
        };
    }

    /**
     * @param callable(string, string): bool $verifier
     */
    public function setTotpVerifier(callable $verifier): void
    {
        $this->totpVerifier = $verifier;
    }

    public function verify(string $challengeJwt, string $code): TwoFactorLoginResult
    {
        try {
            $userId = $this->challengeService->verifyChallenge($challengeJwt);
        } catch (DomainException $e) {
            return new TwoFactorLoginResult(TwoFactorLoginStatus::ChallengeInvalid, reason: $e->getMessage());
        }

        $user = User::findByID($userId);
        if ($user === false || !($user->is_active ?? true)) {
            return new TwoFactorLoginResult(TwoFactorLoginStatus::UserInactive);
        }

        $row = $this->twoFactorRepo->findByUserIdAndMethod($userId, TwoFactorRepository::METHOD_APP);
        if ($row === null || (int) ($row['enabled'] ?? 0) !== 1) {
            return new TwoFactorLoginResult(TwoFactorLoginStatus::NotEnabled);
        }

        $secret = (string) ($row['secret_key'] ?? '');
        if ($secret === '' || !($this->totpVerifier)($secret, $code)) {
            return new TwoFactorLoginResult(TwoFactorLoginStatus::InvalidCode);
        }

        return new TwoFactorLoginResult(TwoFactorLoginStatus::Ok, user: $user);
    }
}
