<?php

declare(strict_types=1);

namespace Framework\Auth;

use Framework\Models\AbstractUser;

final class TwoFactorLoginResult
{
    public function __construct(
        public readonly TwoFactorLoginStatus $status,
        public readonly ?AbstractUser $user = null,
        public readonly ?string $reason = null,
    ) {
    }
}
