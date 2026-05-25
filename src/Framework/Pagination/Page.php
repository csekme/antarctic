<?php

declare(strict_types=1);

namespace Framework\Pagination;

/**
 * Standard `{data, meta}` envelope for paginated list responses. Construct it
 * via {@see Page::of()} with the loaded slice + the total row count; the meta
 * (`page`, `perPage`, `total`, `totalPages`) is computed automatically from
 * the {@see PaginationParams} that produced the query.
 *
 * The envelope is intentionally strict: every list endpoint returns the same
 * shape so the React client can share a single `<Pagination>` component.
 *
 * @template T
 */
final class Page
{
    /**
     * @param list<T> $data
     * @param array{page:int,perPage:int,total:int,totalPages:int} $meta
     */
    private function __construct(
        public readonly array $data,
        public readonly array $meta,
    ) {
    }

    /**
     * @template U
     * @param list<U> $data
     * @return self<U>
     */
    public static function of(array $data, int $total, PaginationParams $params): self
    {
        $perPage = max(1, $params->perPage);
        $totalPages = $total === 0 ? 0 : (int) ceil($total / $perPage);
        return new self($data, [
            'page' => $params->page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * @return array{data:list<T>, meta:array{page:int,perPage:int,total:int,totalPages:int}}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'meta' => $this->meta,
        ];
    }
}
