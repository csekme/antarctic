<?php

declare(strict_types=1);

namespace Framework\Auth;

/**
 * Password-verify boundary. The default implementation just delegates to
 * PHP's native password_verify(), but having it behind an interface lets the
 * LoginService be tested deterministically — a spy can count calls to assert
 * that the user-enumeration mitigation (verify even when the user does not
 * exist) actually happens.
 */
interface PasswordVerifier
{
    public function verify(string $password, string $hash): bool;
}
