<?php

declare(strict_types=1);

namespace Framework\Auth;

use Framework\Dal;
use Framework\Models\User;
use Framework\Token;
use PDO;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Activates a newly-registered user by HMAC-comparing the raw token from
 * the verify-email URL against the stored activation_hash.
 */
final class EmailVerificationService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function verify(string $rawToken): VerifyResult
    {
        try {
            $hash = (new Token($rawToken))->getHash();
        } catch (Throwable $e) {
            $this->logger->error('auth.verify_email.hash_failed', ['error' => $e->getMessage()]);
            return new VerifyResult(VerifyStatus::Misconfigured);
        }

        $pdo = Dal::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM user WHERE activation_hash = :h');
        $stmt->bindValue(':h', $hash);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            $this->logger->info('auth.verify_email.fail', ['reason' => 'unknown_token']);
            return new VerifyResult(VerifyStatus::Unknown);
        }

        User::activateByActivationHash($hash);
        $userId = (int) $row['id'];
        $this->logger->info('auth.verify_email.success', ['user_id' => $userId]);

        return new VerifyResult(VerifyStatus::Ok, userId: $userId);
    }
}
