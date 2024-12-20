<?php

namespace Framework;

use Exception;

class Config {

    private static mixed $_config = null;
    private const  HTTP_PROTOCOL = 'http';
    public const  APPLICATION_CONFIG_FILE = '/Application/application.json';

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

    /**
     * Use cache for limit database interaction
     * @return bool default is false
     */
    public static function useCache() : bool {
        $_config = Config::get_config();
        if (isset($_config['framework']['cache'])) {
            return $_config['framework']['cache'];
        }
        return false;
    }

    public static function useCoreController() : bool {
        $_config = Config::get_config();
        if (isset($_config['framework']['useCoreControllers'])) {
            return $_config['framework']['useCoreControllers'];
        }
        return false;
    }
    public static function get_interceptors(): array {
        $config = Config::get_config();
        return $config['application']['interceptors'] ?? [];
    }

    public static function show_errors(): bool {
        $_config = Config::get_config();
        if (isset($_config['framework']['showErrors'])) {
            return $_config['framework']['showErrors'];
        }
        return false;
    }

}