<?php

namespace Framework;

class Session
{
    /**
     * Get session value
     * @param $key
     * @return mixed null if not exist
     */
    public static function get($key) : mixed
    {
        if (!isset($_SESSION[$key])) {
            return null;
        }
        return $_SESSION[$key];
    }

    /**
     * Set session value
     * @param $key
     * @param $value
     * @return void
     */
    public static function set($key, $value) : void
    {
        $_SESSION[$key] = $value;
    }

}