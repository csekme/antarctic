<?php

declare(strict_types=1);

namespace Tests\Framework\Pagination;

use Framework\Pagination\Page;
use Framework\Pagination\PaginationParams;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    public function testEnvelopeComputesTotalPages(): void
    {
        $params = new PaginationParams(page: 2, perPage: 10);
        $page = Page::of(data: [['id' => 11], ['id' => 12]], total: 25, params: $params);

        $this->assertSame([['id' => 11], ['id' => 12]], $page->data);
        $this->assertSame([
            'page' => 2,
            'perPage' => 10,
            'total' => 25,
            'totalPages' => 3,
        ], $page->meta);
    }

    public function testEmptyResultSetHasZeroTotalPages(): void
    {
        $params = new PaginationParams(page: 1, perPage: 20);
        $page = Page::of([], total: 0, params: $params);

        $this->assertSame([], $page->data);
        $this->assertSame(0, $page->meta['totalPages']);
        $this->assertSame(0, $page->meta['total']);
    }

    public function testExactPageBoundaryDoesNotInflateTotalPages(): void
    {
        $params = new PaginationParams(page: 5, perPage: 10);
        $page = Page::of([], total: 50, params: $params);

        $this->assertSame(5, $page->meta['totalPages']);
    }

    public function testToArrayProducesEnvelopeShape(): void
    {
        $params = new PaginationParams(page: 1, perPage: 2);
        $page = Page::of(['a', 'b'], total: 2, params: $params);

        $this->assertSame([
            'data' => ['a', 'b'],
            'meta' => [
                'page' => 1,
                'perPage' => 2,
                'total' => 2,
                'totalPages' => 1,
            ],
        ], $page->toArray());
    }
}
