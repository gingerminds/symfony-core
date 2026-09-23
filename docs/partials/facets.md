# Facets

Faceted search: each filter option shows how many results it would yield ("Concerts (12)"),
computed against every **other** active filter. Built on `getFilters()`.

## `AbstractFacetRepository`

Extend it instead of `AbstractRepository`:

```php
/**
 * @extends AbstractFacetRepository<Event>
 */
final class EventRepository extends AbstractFacetRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }
}
```

- `buildFacetedQuery(ListQuery $query, array $excludedKeys = []): QueryBuilder` — the list
  query (search, every filter handler, `configureListQueryBuilder()` context) without the
  excluded keys, sort and pagination. Build any facet on it.
- `getDateFacetStats(ListQuery $query, string $property): array{min, max, years}` — bounds and
  distinct years of a date field (years computed in PHP, database-agnostic).
- `getAssociationFacetCounts(ListQuery $query, string $association): array<id, total>` —
  counts per related id (categories...).
- `extraFacetQuerySetup(QueryBuilder $qb)` — hook for anything every facet query needs.
- `COUNT_TOTAL` — `COUNT(DISTINCT %s) AS total` for your own grouped counts.

> Change from the Laravel core: facets reuse the registered filter handlers instead of a
> separate dispatch, so a filter behaves identically in lists and facets. Declare
> relation filters as `select-entity`.

`Gingerminds\CoreBundle\Repository\Facet\DateFacetCalculator::compute(QueryBuilder, string $dqlField)`
is also available directly.

## `AbstractFilterComputeService`

Turns facet stats into the front-end shape:

```php
/**
 * @extends AbstractFilterComputeService<Event>
 */
final class EventFilterComputeService extends AbstractFilterComputeService
{
    public function __construct(private readonly EventRepository $events) {}

    protected function getEntityClass(): string
    {
        return Event::class;
    }

    protected function dateFacetStats(ListQuery $query): array
    {
        return ['publishedAt' => $this->events->getDateFacetStats($query, 'publishedAt')];
    }

    /**
     * @return list<array{value: int, label: string, total: int}>
     */
    public function categories(ListQuery $query): array
    {
        return $this->resolveCategoryOptions(
            Category::class,
            $this->events->getAssociationFacetCounts($query, 'categories'),
            $this->normalizeIds($this->activeFilterValue($query, 'categories')),
        );
    }
}
```

Helpers: `computeDateFilters()`, `activeFilterValue()`, `mergeWithSelected()`,
`normalizeIds()`, `resolveCategoryOptions()` (same behaviour as the Laravel core).

## Exposing computed filters through the API

Store them from the provider in `Gingerminds\CoreBundle\ApiPlatform\Filter\ComputedFilterStore`;
`InjectComputedFiltersListener` adds them as a `filters` key of the collection response:

```php
final class EventProvider extends ResourceProvider
{
    public function __construct(
        EventRepository $events,
        RequestStack $requestStack,
        private readonly EventFilterComputeService $filters,
        private readonly ComputedFilterStore $store,
    ) {
        parent::__construct($events, $requestStack);
    }

    protected function configureListQuery(ListQuery $query, Operation $operation, array $uriVariables, array $context): ListQuery
    {
        if ($operation instanceof CollectionOperationInterface) {
            $this->store->set(Event::class, [
                ...$this->filters->computeDateFilters($query),
                'categories' => ['type' => 'select-entity', 'options' => $this->filters->categories($query)],
            ]);
        }

        return $query;
    }
}
```

> Change from the Laravel core: a single request-scoped store keyed by resource class
> replaces the per-resource `FilterStore` subclasses and `AbstractInjectFiltersMiddleware`
> subclasses — nothing to register on the operation.

Entries without options are dropped; a bare JSON list is wrapped as `{member, filters}`.
