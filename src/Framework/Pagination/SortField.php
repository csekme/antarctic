<?php

declare(strict_types=1);

namespace Framework\Pagination;

/**
 * A single `?sort=` directive — a field name plus a direction. Directions are
 * normalised to lowercase `asc` / `desc`; the parser drives them off the
 * leading sign in the query (`-createdAt` → desc, `createdAt` or `+createdAt`
 * → asc).
 */
final class SortField
{
    public const ASC = 'asc';
    public const DESC = 'desc';

    public function __construct(
        public readonly string $field,
        public readonly string $direction = self::ASC,
    ) {
    }

    public function isDescending(): bool
    {
        return $this->direction === self::DESC;
    }
}
