<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\Repository;

use Gingerminds\CoreBundle\Repository\ListQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ListQueryTest extends TestCase
{
    public function testFromRequest(): void
    {
        $query = ListQuery::fromRequest(Request::create('/admin/users', 'GET', [
            'page' => '3',
            'itemsPerPage' => '25',
            'sortBy' => 'email',
            'sort' => 'DESC',
            'filters' => ['search' => 'john', 'active' => 'yes'],
        ]));

        self::assertSame(3, $query->page);
        self::assertSame(25, $query->itemsPerPage);
        self::assertSame('email', $query->sortBy);
        self::assertSame(ListQuery::SORT_DESC, $query->sort);
        self::assertSame(['search' => 'john', 'active' => 'yes'], $query->filters);
    }

    public function testInvalidValuesFallBackToDefaults(): void
    {
        $query = ListQuery::fromRequest(Request::create('/', 'GET', [
            'page' => '-4',
            'sort' => 'random',
            'filters' => 'not-an-array',
        ]));

        self::assertSame(1, $query->page);
        self::assertNull($query->itemsPerPage);
        self::assertNull($query->sortBy);
        self::assertSame(ListQuery::SORT_ASC, $query->sort);
        self::assertSame([], $query->filters);
    }

    public function testImmutableModifiers(): void
    {
        $query = new ListQuery(page: 2, sortBy: 'name', filters: ['a' => 1, 'b' => 2]);

        $modified = $query->withFilter('c', 3)->withoutFilters('a')->withSort('id', 'desc')->withPage(0);

        self::assertSame(['a' => 1, 'b' => 2], $query->filters);
        self::assertSame(['b' => 2, 'c' => 3], $modified->filters);
        self::assertSame('id', $modified->sortBy);
        self::assertSame(ListQuery::SORT_DESC, $modified->sort);
        self::assertSame(1, $modified->page);
    }

    public function testToQueryParameters(): void
    {
        $query = new ListQuery(page: 2, itemsPerPage: 50, sortBy: 'name', sort: 'desc', filters: ['search' => 'x']);

        self::assertSame([
            'page' => 2,
            'itemsPerPage' => 50,
            'sortBy' => 'name',
            'sort' => 'desc',
            'filters' => ['search' => 'x'],
        ], $query->toQueryParameters());
        self::assertSame([], new ListQuery()->toQueryParameters());
    }
}
