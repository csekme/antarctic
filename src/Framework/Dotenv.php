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
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = strtoupper(trim($name));
            $value = trim($value);

            // Strip surrounding quotes if present.
            if (
                strlen($value) >= 2
                && (
                    (str_starts_with($value, '"') && str_ends_with($value, '"'))
                    || (str_starts_with($value, "'") && str_ends_with($value, "'"))
                )
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$name] = $value;
            // Also expose via getenv() so config files reading getenv('FOO') work.
            putenv($name . '=' . $value);
        }
    }
}