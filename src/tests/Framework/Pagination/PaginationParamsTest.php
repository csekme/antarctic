<?php

declare(strict_types=1);

namespace Tests\Framework\Pagination;

use Framework\Pagination\PaginationParams;
use Framework\Pagination\SortField;
use Framework\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

final class PaginationParamsTest extends TestCase
{
    public function testEmptyQueryYieldsDefaults(): void
    {
        $params = PaginationParams::fromQuery([]);

        $this->assertSame(1, $params->page);
        $this->assertSame(PaginationParams::DEFAULT_PER_PAGE, $params->perPage);
        $this->assertSame([], $params->sort);
        $this->assertSame([], $params->filter);
        $this->assertSame(0, $params->offset());
    }

    public function testStringIntsAreCoerced(): void
    {
        $params = PaginationParams::fromQuery(['page' => '3', 'perPage' => '50']);

        $this->assertSame(3, $params->page);
        $this->assertSame(50, $params->perPage);
        $this->assertSame(100, $params->offset()); // (3-1)*50
    }

    public function testPerPageAboveMaxIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        try {
            PaginationParams::fromQuery(['perPage' => '500'], maxPerPage: 100);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('perPage', $e->getErrors());
            $this->assertStringContainsString('<= 100', $e->getErrors()['perPage'][0]);
            throw $e;
        }
    }

    public function testNegativePageIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        try {
            PaginationParams::fromQuery(['page' => '-1']);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('page', $e->getErrors());
            throw $e;
        }
    }

    public function testNonNumericPageIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        try {
            PaginationParams::fromQuery(['page' => 'abc']);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('page', $e->getErrors());
            throw $e;
        }
    }

    public function testSortParsesSignedFields(): void
    {
        $params = PaginationParams::fromQuery(['sort' => '-createdAt,+name,email']);

        $this->assertCount(3, $params->sort);
        $this->assertSame('createdAt', $params->sort[0]->field);
        $this->assertSame(SortField::DESC, $params->sort[0]->direction);
        $this->assertTrue($params->sort[0]->isDescending());

        $this->assertSame('name', $params->sort[1]->field);
        $this->assertSame(SortField::ASC, $params->sort[1]->direction);

        $this->assertSame('email', $params->sort[2]->field);
        $this->assertFalse($params->sort[2]->isDescending());
    }

    public function testSortWhitespaceAndEmptyEntriesAreTolerated(): void
    {
        $params = PaginationParams::fromQuery(['sort' => ' -createdAt , ,  +name ']);

        $this->assertCount(2, $params->sort);
        $this->assertSame('createdAt', $params->sort[0]->field);
        $this->assertSame('name', $params->sort[1]->field);
    }

    public function testSortRejectsInvalidIdentifier(): void
    {
        $this->expectException(ValidationException::class);
        try {
            PaginationParams::fromQuery(['sort' => '-9bad,+ok']);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sort', $e->getErrors());
            throw $e;
        }
    }

    public function testFilterIsAssociativeStringMap(): void
    {
        $params = PaginationParams::fromQuery([
            'filter' => ['status' => 'active', 'role' => 'admin', 'count' => 7],
        ]);

        $this->assertSame(['status' => 'active', 'role' => 'admin', 'count' => '7'], $params->filter);
    }

    public function testFilterRejectsNonScalarValue(): void
    {
        $this->expectException(ValidationException::class);
        try {
            PaginationParams::fromQuery(['filter' => ['nested' => ['x' => 1]]]);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('filter', $e->getErrors());
            throw $e;
        }
    }

    public function testFilterRejectsNonArrayShape(): void
    {
        $this->expectException(ValidationException::class);
        try {
            PaginationParams::fromQuery(['filter' => 'oops']);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('filter', $e->getErrors());
            throw $e;
        }
    }

    public function testCustomDefaultPerPageIsUsedWhenAbsent(): void
    {
        $params = PaginationParams::fromQuery([], defaultPerPage: 10);
        $this->assertSame(10, $params->perPage);
    }
}
