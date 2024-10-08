<?php

namespace Framework;

use Exception;
use Random\RandomException;

/**
 * Unique random tokens
 *
 * PHP version 8.0
 * @category Framework
 * @package  Framework
 * @since 1.0
 * @license GPL-3.0-or-later
 * @author Krisztián Csekme
 */
class Token
{
    /**
     * The token value
     */
    protected mixed $token;

    /**
     * Class constructor. Create a new random token or assign an existing one if passed in.
     *
     * @param null $token_value
     * @throws RandomException
     */
    public function __construct($token_value = null)
    {
        if ($token_value) {
            $this->token = $token_value;
        } else {
            $this->token = bin2hex(random_bytes(16));  // 16 bytes = 128 bits = 32 hex characters
        }
    }

    /**
     * Get the token value
     *
     * @return string The value
     */
    public function getValue(): string
    {
        return $this->token;
    }

    /**
     * Get the hashed token value
     *
     * @return string The hashed value
     * @throws Exception
     */
    public function getHash(): string
    {
        if (isset(Config::get_config()["application"]["secretKey"])) {
            return hash_hmac('sha256', $this->token, Config::get_config()["application"]["secretKey"]);  // sha256 = 64 chars
        } else {
            throw new Exception(message: 'application.secretKey has not set', code: 500);
        }
    }
}
