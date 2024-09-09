<?php

namespace Framework;

use Exception;

class Config {

    private static mixed $_config = null;
    private const string HTTP_PROTOCOL = 'http';
    public const string APPLICATION_CONFIG_FILE = '/Application/application.json';

    private static function set_config($path) {
        $jsonData = file_get_contents($path);
        Config::$_config = json_decode($jsonData, true); // true paraméterrel asszociatív tömbként kezeli
    }

    public static function get_config() {
        if (Config::$_config === null) {
            if (file_exists(dirname(__DIR__) . Config::APPLICATION_CONFIG_FILE)) {
                Config::set_config( dirname(__DIR__) . Config::APPLICATION_CONFIG_FILE);
            }
        }
        return Config::$_config;
    }

    public static function get_server_protocol()  {
        $_config = Config::get_config();
        return $_config['server']['protocol'] ?? Config::HTTP_PROTOCOL;
    }

}