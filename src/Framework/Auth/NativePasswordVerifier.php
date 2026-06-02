<?php

declare(strict_types=1);

namespace Framework\Auth;

final class NativePasswordVerifier implements PasswordVerifier
{
    /**
     * Dummy bcrypt hash used to keep timing roughly constant when the
     * lookup found no user (no real $hash to compare against).
     */
    public const DUMMY_HASH = '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidi';

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
