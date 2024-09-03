<?php

declare(strict_types=1);

namespace Framework;

/**
 * Class Dotenv
 * @package Framework
 */
class Dotenv
{
    /**
     * Load the environment variables from the file
     * @param string $path
     */
    public function load(string $path): void
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        foreach ($lines as $line) {

            list($name, $value) = explode("=", $line, 2);

            $_ENV[strtoupper($name)] = $value;
        }
    }
}