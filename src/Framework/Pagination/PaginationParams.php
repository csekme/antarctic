<?php

declare(strict_types=1);

namespace Framework\Pagination;

use Framework\Validation\ValidationException;

/**
 * Parsed `?page=…&perPage=…&sort=…&filter[…]=…` query parameters.
 *
 * The convention:
 *   ?page=1           1-based page index, default 1
 *   ?perPage=20       items per page, default 20, clamped to [1, maxPerPage]
 *   ?sort=-createdAt,+name
 *                     comma-separated list of `[+|-]<field>` entries; the
 *                     leading `-` means descending, `+` (or no sign) means
 *                     ascending. Whitespace around commas is tolerated.
 *   ?filter[status]=active&filter[role]=admin
 *                     PHP associative array; values are passed through as
 *                     strings without further parsing. Endpoints are
 *                     responsible for whitelisting accepted keys.
 *
 * Invalid input (non-positive page, non-numeric perPage, etc.) raises a
 * {@see ValidationException} which the error handler middleware maps to a
 * 422 problem+json with field-level diagnostics — same envelope as the DTO
 * validation layer.
 */
final class PaginationParams
{
    public const DEFAULT_PER_PAGE = 20;
    public const DEFAULT_MAX_PER_PAGE = 100;

    /**
     * @param list<SortField> $sort
     * @param array<string, string> $filter
     */
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly array $sort = [],
        public readonly array $filter = [],
    ) {
    }

    /**
     * @param array<string, mixed> $query Raw `$_GET`-style associative array.
     */
    public static function fromQuery(
        array $query,
        int $defaultPerPage = self::DEFAULT_PER_PAGE,
        int $maxPerPage = self::DEFAULT_MAX_PER_PAGE,
    ): self {
        /** @var array<string, list<string>> $errors */
        $errors = [];

        $page = self::readInt($query['page'] ?? null, 1, 'page', $errors);
        if ($page !== null && $page < 1) {
            $errors['page'][] = 'page must be >= 1.';
        }

        $perPage = self::readInt($query['perPage'] ?? null, $defaultPerPage, 'perPage', $errors);
        if ($perPage !== null && $perPage < 1) {
            $errors['perPage'][] = 'perPage must be >= 1.';
        } elseif ($perPage !== null && $perPage > $maxPerPage) {
            $errors['perPage'][] = sprintf('perPage must be <= %d.', $maxPerPage);
        }

        $sort = self::parseSort($query['sort'] ?? null, $errors);
        $filter = self::parseFilter($query['filter'] ?? null, $errors);

        if ($errors !== []) {
            throw new ValidationException($errors, 'Invalid pagination parameters.');
        }

        /** @var int $page */
        /** @var int $perPage */
        return new self($page, $perPage, $sort, $filter);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * @param array<string, list<string>> $errors
     */
    private static function readInt(mixed $raw, int $default, string $field, array &$errors): ?int
    {
        if ($raw === null || $raw === '') {
            return $default;
        }
        if (is_int($raw)) {
            return $raw;
        }
        if (is_string($raw) && preg_match('/^-?\d+$/', $raw) === 1) {
            return (int) $raw;
        }
        $errors[$field][] = sprintf('%s must be an integer.', $field);
        return null;
    }

    /**
     * @param array<string, list<string>> $errors
     * @return list<SortField>
     */
    private static function parseSort(mixed $raw, array &$errors): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (!is_string($raw)) {
            $errors['sort'][] = 'sort must be a string.';
            return [];
        }
        $out = [];
        foreach (explode(',', $raw) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            $direction = SortField::ASC;
            if ($entry[0] === '-') {
                $direction = SortField::DESC;
                $entry = substr($entry, 1);
            } elseif ($entry[0] === '+') {
                $entry = substr($entry, 1);
            }
            if ($entry === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $entry) !== 1) {
                $errors['sort'][] = sprintf('sort field "%s" is not a valid identifier.', $entry);
                continue;
            }
            $out[] = new SortField($entry, $direction);
        }
        return $out;
    }

    /**
     * @param array<string, list<string>> $errors
     * @return array<string, string>
     */
    private static function parseFilter(mixed $raw, array &$errors): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (!is_array($raw)) {
            $errors['filter'][] = 'filter must be an associative array (use `filter[key]=value`).';
            return [];
        }
        $out = [];
        foreach ($raw as $key => $value) {
            if (!is_string($key)) {
                $errors['filter'][] = 'filter keys must be strings.';
                continue;
            }
            if (is_scalar($value)) {
                $out[$key] = (string) $value;
            } else {
                $errors['filter'][] = sprintf('filter[%s] must be scalar.', $key);
            }
        }
        return $out;
    }
}
