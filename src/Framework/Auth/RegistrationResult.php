<?php

declare(strict_types=1);

namespace Framework\Auth;

final class RegistrationResult
{
    public function __construct(
        public readonly RegistrationStatus $status,
        public readonly ?int $userId = null,
        public readonly ?string $verificationLink = null,
    ) {
    }
}
