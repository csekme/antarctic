<?php

declare(strict_types=1);

namespace Framework\Routing;

/**
 * A `Router::match` válasza. Három állapota van:
 *
 *   - FOUND($params)            — talált egy route-ot a megfelelő HTTP metódussal.
 *   - METHOD_NOT_ALLOWED($a)    — van match a URL-re, de más metódus(ok)
 *                                 alatt. A `$allowedMethods` lista a CORS /
 *                                 405 válaszhoz használandó `Allow` headerhez.
 *   - NOT_FOUND                 — semmilyen route nem illeszkedik a URL-re.
 *
 * Immutable value object. A három állapotot a static factory metódusok
 * (`found`, `methodNotAllowed`, `notFound`) hozzák létre — explicit, hogy
 * a hívó eldöntse, melyik ágra van szüksége.
 */
final class MatchResult
{
    public const FOUND = 'found';
    public const METHOD_NOT_ALLOWED = 'method_not_allowed';
    public const NOT_FOUND = 'not_found';

    /**
     * @param array<string, mixed> $params
     * @param list<string> $allowedMethods
     */
    private function __construct(
        public readonly string $status,
        public readonly array $params = [],
        public readonly array $allowedMethods = [],
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function found(array $params): self
    {
        return new self(self::FOUND, $params);
    }

    /**
     * @param list<string> $allowedMethods
     */
    public static function methodNotAllowed(array $allowedMethods): self
    {
        return new self(self::METHOD_NOT_ALLOWED, [], array_values(array_unique($allowedMethods)));
    }

    public static function notFound(): self
    {
        return new self(self::NOT_FOUND);
    }

    public function isFound(): bool
    {
        return $this->status === self::FOUND;
    }

    public function isMethodNotAllowed(): bool
    {
        return $this->status === self::METHOD_NOT_ALLOWED;
    }

    public function isNotFound(): bool
    {
        return $this->status === self::NOT_FOUND;
    }
}
