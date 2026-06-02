<?php

declare(strict_types=1);

namespace Framework\Auth;

use Framework\Models\AbstractUser;
use Framework\Models\User;
use Framework\Repositories\TwoFactorRepository;

/**
 * The first step of the login flow: validate the email + password pair and
 * decide whether to issue a session right away or to demand a TOTP code.
 *
 * User-enumeration mitigation: password_verify ALWAYS runs, even on the
 * "user not found" branch, so the response time does not leak whether the
 * email is registered. The PasswordVerifier interface makes this testable
 * via a call-counting spy.
 */
final class LoginService
{
    public function __construct(
        private readonly TwoFactorRepository $twoFactorRepo,
        private readonly PasswordVerifier $passwordVerifier = new NativePasswordVerifier(),
    ) {
    }

    public function attempt(string $emailOrUsername, string $password): LoginResult
    {
        $user = User::findByUsernameOrEmail($emailOrUsername);
        $passwordOk = false;

        if ($user instanceof AbstractUser && is_string($user->password_hash ?? null)) {
            $passwordOk = $this->passwordVerifier->verify($password, (string) $user->password_hash);
        } else {
            // Dummy verify so timing does not betray missing users.
            $this->passwordVerifier->verify($password, NativePasswordVerifier::DUMMY_HASH);
        }

        if (!$passwordOk || !$user instanceof AbstractUser) {
            return new LoginResult(LoginStatus::InvalidCredentials);
        }

        if (!($user->is_active ?? false)) {
            return new LoginResult(LoginStatus::Unverified);
        }

        // Strip transient security fields once we know the credentials are good.
        unset($user->password_hash, $user->activation_hash, $user->password_reset_hash);

        $methods = $this->twoFactorRepo->enabledMethods((int) $user->id);
        if ($methods !== []) {
            return new LoginResult(LoginStatus::TwoFactorRequired, user: $user, methods: $methods);
        }

        return new LoginResult(LoginStatus::Ok, user: $user);
    }
}
