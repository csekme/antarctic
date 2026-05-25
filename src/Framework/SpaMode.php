<?php

declare(strict_types=1);

namespace Framework;

/**
 * Az Antarctic háromféle SPA deploy-módot támogat:
 *
 *   - SEPARATE: a SPA külön origin-en fut, a backend csak az API
 *     endpointokat szállítja. CORS allow-list aktív.
 *   - EMBEDDED: a backend webrootja alá (`src/html/app/`) bemásolt
 *     SPA build-et szolgáljuk, ugyanazon origin-en a backenddel.
 *     CORS-ra nincs szükség.
 *   - BOTH: fejlesztéshez — mindkét deploy-mód működik egyszerre.
 *
 * A módot az `APP_SPA_MODE` env változó dönti el. A `.htaccess` ezen
 * túlmenően a fájl-jelenlétre (létezik-e `app/index.html`) is támaszkodik,
 * de a PHP-rétegnek (CORS, info endpoint) erre az enumra van szüksége.
 */
enum SpaMode: string
{
    case SEPARATE = 'separate';
    case EMBEDDED = 'embedded';
    case BOTH = 'both';

    public static function current(): self
    {
        return self::fromEnv(self::readEnv('APP_SPA_MODE'));
    }

    public static function fromEnv(?string $raw): self
    {
        $value = is_string($raw) ? strtolower(trim($raw)) : '';
        return match ($value) {
            'embedded' => self::EMBEDDED,
            'both' => self::BOTH,
            default => self::SEPARATE,
        };
    }

    public function servesSpa(): bool
    {
        return $this !== self::SEPARATE;
    }

    public function requiresCors(): bool
    {
        return $this !== self::EMBEDDED;
    }

    private static function readEnv(string $name): ?string
    {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            return $value;
        }
        if (isset($_ENV[$name]) && is_string($_ENV[$name]) && $_ENV[$name] !== '') {
            return $_ENV[$name];
        }
        if (isset($_SERVER[$name]) && is_string($_SERVER[$name]) && $_SERVER[$name] !== '') {
            return $_SERVER[$name];
        }
        return null;
    }
}
