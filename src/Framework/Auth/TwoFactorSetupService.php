<?php

declare(strict_types=1);

namespace Framework\Auth;

use Framework\Models\AbstractUser;
use Framework\Models\User;
use Framework\Repositories\TwoFactorRepository;
use Framework\TwoFactor;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The 2FA enrollment aggregate: enroll → confirm → (later) disable. All
 * three operations share the same repo, the same TOTP verifier and the
 * same audit logger, so they live as methods on one service rather than
 * three thin classes with identical dependencies.
 */
final class TwoFactorSetupService
{
    private const ISSUER = 'Antarctic Framework';

    /** @var callable(string $secret, string $code): bool */
    private $totpVerifier;
    /** @var callable(): array{secret: string, qr_data_uri: string} */
    private $enroller;
    private PasswordVerifier $passwordVerifier;

    public function __construct(
        private readonly TwoFactorRepository $twoFactorRepo,
        private readonly LoggerInterface $logger,
        ?callable $totpVerifier = null,
        ?callable $enroller = null,
        ?PasswordVerifier $passwordVerifier = null,
    ) {
        $this->totpVerifier = $totpVerifier ?? static function (string $secret, string $code): bool {
            return (new TwoFactor())->verifyCode($secret, $code);
        };
        $this->enroller = $enroller ?? static function (): array {
            $tfa = new TwoFactor();
            $secret = $tfa->generateSecretKey();
            return ['secret' => $secret, 'qr_data_uri' => $tfa->getQRCodeImageAsDataUri($secret)];
        };
        $this->passwordVerifier = $passwordVerifier ?? new NativePasswordVerifier();
    }

    /**
     * @param callable(string, string): bool $verifier
     */
    public function setTotpVerifier(callable $verifier): void
    {
        $this->totpVerifier = $verifier;
    }

    /**
     * @param callable(): array{secret: string, qr_data_uri: string} $enroller
     */
    public function setEnroller(callable $enroller): void
    {
        $this->enroller = $enroller;
    }

    public function enroll(int $userId, string $email): EnrollResult
    {
        $existing = $this->twoFactorRepo->findByUserIdAndMethod($userId, TwoFactorRepository::METHOD_APP);
        if ($existing !== null && (int) ($existing['enabled'] ?? 0) === 1) {
            return new EnrollResult(EnrollStatus::AlreadyEnabled);
        }

        try {
            $generated = ($this->enroller)();
            $secret = $generated['secret'];
            $qr = $generated['qr_data_uri'];
        } catch (Throwable $e) {
            $this->logger->error('auth.2fa.enroll.failed', ['error' => $e->getMessage()]);
            return new EnrollResult(EnrollStatus::Failed);
        }

        $this->twoFactorRepo->enroll($userId, TwoFactorRepository::METHOD_APP, $secret, enabled: false);

        $otpauth = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode(self::ISSUER),
            rawurlencode($email),
            rawurlencode($secret),
            rawurlencode(self::ISSUER),
        );

        $this->logger->info('auth.2fa.enroll', ['user_id' => $userId]);

        return new EnrollResult(EnrollStatus::Started, secret: $secret, otpauthUri: $otpauth, qrDataUri: $qr);
    }

    public function confirm(int $userId, string $code): ConfirmResult
    {
        $row = $this->twoFactorRepo->findByUserIdAndMethod($userId, TwoFactorRepository::METHOD_APP);
        if ($row === null) {
            return new ConfirmResult(ConfirmStatus::NotStarted);
        }
        if ((int) ($row['enabled'] ?? 0) === 1) {
            return new ConfirmResult(ConfirmStatus::AlreadyEnabled);
        }

        $secret = (string) ($row['secret_key'] ?? '');
        if ($secret === '' || !($this->totpVerifier)($secret, $code)) {
            $this->logger->info('auth.2fa.confirm.fail', ['user_id' => $userId]);
            return new ConfirmResult(ConfirmStatus::InvalidCode);
        }

        $this->twoFactorRepo->setEnabled($userId, TwoFactorRepository::METHOD_APP, true);
        $this->logger->info('auth.2fa.confirm', ['user_id' => $userId]);

        return new ConfirmResult(ConfirmStatus::Enabled);
    }

    public function disable(int $userId, string $password): DisableResult
    {
        $user = User::findByID($userId);
        if (!$user instanceof AbstractUser || !is_string($user->password_hash ?? null)) {
            return new DisableResult(DisableStatus::UserMissing);
        }
        if (!$this->passwordVerifier->verify($password, (string) $user->password_hash)) {
            return new DisableResult(DisableStatus::WrongPassword);
        }

        $this->twoFactorRepo->setEnabled($userId, TwoFactorRepository::METHOD_APP, false);
        $this->logger->info('auth.2fa.disable', ['user_id' => $userId]);

        return new DisableResult(DisableStatus::Disabled);
    }
}
