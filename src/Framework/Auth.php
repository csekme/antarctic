<?php

namespace Framework;
use Framework\Models\AbstractUser;

class Auth
{
    /**
     * Login the user
     * @param AbstractUser $user The user model
     * @param bool $remember_me
     * @return void
     * @throws \Exception
     */
    public static function login(AbstractUser $user, bool $remember_me = false): void
    {
        //Prevent session fixation attacks
        session_regenerate_id(true);

        $_SESSION['user'] = $user;

        if ($remember_me) {
            //Successfull inserted token hash
            if ($user->rememberLogin()) {
                setcookie('remember_me', $user->remember_token, $user->expiry_timestamp, '/');
            }
        }
    }

    /**
     * Logout the user
     * @return void
     * @throws \Exception
     */
    public static function logout(): void
    {
        // Unset all of the session variable
        $_SESSION = [];

        //Delete the session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        //Finally destroy the session
        session_destroy();

        static::forgetLogin();
    }


    /**
     * Remember the original-requested page in the session
     * @return void
     */
    public static function rememberRequestedPage(): void
    {
        $_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
    }

    /**
     * Get the originally-requested page to return to after requiring login,
     * or default to the homepage
     *
     * @return string
     */
    public static function getReturnToPage(): string
    {
        return $_SESSION['return_to'] ?? '/';
    }

    /**
     * Get the current logged-in user, from the session or the remember-me cookie
     *
     * @return AbstractUser|null The user model or null if not logged in
     * @throws \Exception
     */
    public static function getUser() : AbstractUser | null
    {
        return $_SESSION['user'] ?? static::loginFromRememberCookie();
    }

    /**
     * Login the user from a remembered login cookie
     * @return AbstractUser|null The user model if login cookie found; null otherwise
     * @throws \Exception
     */
    protected static function loginFromRememberCookie(): AbstractUser | null
    {
        $cookie = $_COOKIE['remember_me'] ?? false;

        if ($cookie) {
            $remembered_login = RememberedLogin::findByToken($cookie);

            if ($remembered_login && !$remembered_login->hasExpired()) {
                $user = $remembered_login->getUser();
                static::login($user);
                $user->options = $remembered_login->options;
                return $user;
            }
        }
        return null;
    }

    /**
     * Forget the remembered login, if present
     * @return void
     * @throws \Exception
     */
    protected static function forgetLogin(): void
    {
        $cookie = $_COOKIE['remember_me'] ?? false;
        if ($cookie) {
            $remembered_login = RememberedLogin::findByToken($cookie);
            if ($remembered_login) {
                $remembered_login->delete();
            }
        }
        setcookie('remember_me', '', time() - 3600); // set to expire in the past
    }

}