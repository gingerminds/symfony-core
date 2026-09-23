<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository;

use Symfony\Component\HttpFoundation\Request;

use function array_key_exists;

final readonly class ListQuery
{
    public const string SORT_ASC = 'asc';
    public const string SORT_DESC = 'desc';
    public const string SEARCH_FILTER = 'search';
    public const string ID_FILTER = 'id';

    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public int $page = 1,
        public ?int $itemsPerPage = null,
        public ?string $sortBy = null,
        public string $sort = self::SORT_ASC,
        public array $filters = [],
        public bool $paginate = true,
    ) {
    }

    /**
     * Reads `page`, `itemsPerPage`, `sortBy`, `sort` and `filters[...]`.
     */
    public static function fromRequest(Request $request): self
    {
        $query = $request->query;

        $page = max(1, $query->getInt('page', 1));
        $itemsPerPage = $query->has('itemsPerPage') ? max(1, $query->getInt('itemsPerPage')) : null;

        $sortBy = $query->get('sortBy');
        $sort = strtolower((string) $query->get('sort', self::SORT_ASC));

        $filters = $query->has('filters') && \is_array($query->all()['filters'] ?? null)
            ? $query->all('filters')
            : [];

        return new self(
            page: $page,
            itemsPerPage: $itemsPerPage,
            sortBy: \is_string($sortBy) && '' !== $sortBy ? $sortBy : null,
            sort: self::SORT_DESC === $sort ? self::SORT_DESC : self::SORT_ASC,
            filters: $filters,
        );
    }

    public function hasFilter(string $key): bool
    {
        return array_key_exists($key, $this->filters);
    }

    public function getFilter(string $key, mixed $default = null): mixed
    {
        return $this->filters[$key] ?? $default;
    }

    public function withFilter(string $key, mixed $value): self
    {
        return $this->with(filters: [...$this->filters, $key => $value]);
    }

    public function withoutFilters(string ...$keys): self
    {
        return $this->with(filters: array_diff_key($this->filters, array_flip($keys)));
    }

    public function withPage(int $page): self
    {
        return $this->with(page: max(1, $page));
    }

    public function withSort(?string $sortBy, string $sort = self::SORT_ASC): self
    {
        return new self(
            page: $this->page,
            itemsPerPage: $this->itemsPerPage,
            sortBy: $sortBy,
            sort: self::SORT_DESC === strtolower($sort) ? self::SORT_DESC : self::SORT_ASC,
            filters: $this->filters,
            paginate: $this->paginate,
        );
    }

    public function withoutPagination(): self
    {
        return $this->with(paginate: false);
    }

    /**
     * @return array<string, mixed>
     */
    public function toQueryParameters(): array
    {
        return array_filter([
            'page' => $this->page > 1 ? $this->page : null,
            'itemsPerPage' => $this->itemsPerPage,
            'sortBy' => $this->sortBy,
            'sort' => null !== $this->sortBy ? $this->sort : null,
            'filters' => [] !== $this->filters ? $this->filters : null,
        ], static fn (mixed $value): bool => null !== $value);
    }

    /**
     * @param array<string, mixed>|null $filters
     */
    private function with(?int $page = null, ?array $filters = null, ?bool $paginate = null): self
    {
        return new self(
            page: $page ?? $this->page,
            itemsPerPage: $this->itemsPerPage,
            sortBy: $this->sortBy,
            sort: $this->sort,
            filters: $filters ?? $this->filters,
            paginate: $paginate ?? $this->paginate,
        );
    }
}
