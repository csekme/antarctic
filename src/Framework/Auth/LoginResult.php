<?php

declare(strict_types=1);

namespace Framework\Auth;

use Framework\Models\AbstractUser;

final class LoginResult
{
    /**
     * @param list<string> $methods
     */
    public function __construct(
        public readonly LoginStatus $status,
        public readonly ?AbstractUser $user = null,
        public readonly array $methods = [],
    ) {
    }
}
