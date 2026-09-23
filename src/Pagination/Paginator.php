<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Pagination;

/**
 * @template T of object
 *
 * @implements \IteratorAggregate<int, T>
 */
final readonly class Paginator implements \IteratorAggregate, \Countable
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        private array $items,
        private int $totalItems,
        private int $page,
        private int $itemsPerPage,
    ) {
    }

    /**
     * @return list<T>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    public function getLastPage(): int
    {
        return max(1, (int) ceil($this->totalItems / max(1, $this->itemsPerPage)));
    }

    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->page < $this->getLastPage();
    }

    public function getFirstItemNumber(): int
    {
        return 0 === $this->totalItems ? 0 : (($this->page - 1) * $this->itemsPerPage) + 1;
    }

    public function getLastItemNumber(): int
    {
        return min($this->totalItems, $this->page * $this->itemsPerPage);
    }

    /**
     * @return \Generator<int, T>
     */
    public function getIterator(): \Generator
    {
        yield from $this->items;
    }

    public function count(): int
    {
        return \count($this->items);
    }
}
