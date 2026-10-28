# Facets

Faceted search: each filter option shows how many results it would yield ("Concerts (12)"),
computed against every **other** active list parameter (see [List parameters](filters.md)).

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

The methods take the valued list parameters (`ApiPlatform\Metadata\Parameters`): on the API the
collection operation's (`$operation->getParameters()`, valued by API Platform), on the admin
`FilterParameters::fromQuery()`.

- `buildFacetedQuery(Parameters $parameters, array $excludedKeys = []): QueryBuilder` — the list
  query (`configureListQueryBuilder()` scope) with the parameters but the excluded keys and the
  sorts, without eager loads. Build any facet on it.
- `getDateFacetStats(Parameters $parameters, string $property): array{min, max, years}` — bounds
  and distinct years of a date field (years computed in PHP, database-agnostic).
- `getAssociationFacetCounts(Parameters $parameters, string $association): array<id, total>` —
  counts per related id (categories...).
- `extraFacetQuerySetup(QueryBuilder $qb)` — hook for anything every facet query needs.
- `COUNT_TOTAL` — `COUNT(DISTINCT %s) AS total` for your own grouped counts.

`Gingerminds\CoreBundle\Repository\Facet\DateFacetCalculator::compute(QueryBuilder, string $dqlField, DisplayTimezone)`
is also available directly. Bounds and years are days of the display timezone.

## Exposing computed filters through the API

A provider decorating the native Doctrine collection provider stores them in
`Gingerminds\CoreBundle\ApiPlatform\Filter\ComputedFilterStore`; `ComputedFiltersCollectionNormalizer`
(decorating the Hydra collection normalizer) adds them as a `filters` key of the JSON-LD collection,
entries without options dropped. A forced filter is set on the operation's parameter before both:

```php
final readonly class EventProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: CollectionProvider::class)]
        private ProviderInterface $collectionProvider,
        private EventRepository $events,
        private ComputedFilterStore $store,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $parameters = $operation->getParameters() ?? new Parameters();
        $parameters->get('status')?->setValue('published');

        $categories = $this->events->getAssociationFacetCounts($parameters, 'categories');

        $this->store->set(Event::class, [
            'publishedAt' => ['type' => 'date', 'options' => $this->events->getDateFacetStats($parameters, 'publishedAt')],
            'categories' => [
                'type' => 'relation',
                'options' => array_map(
                    static fn (int|string $id, int $total): array => ['value' => $id, 'total' => $total],
                    array_keys($categories),
                    $categories,
                ),
            ],
        ]);

        return $this->collectionProvider->provide($operation, $uriVariables, $context);
    }
}
```

`#[ApiResource(operations: [new GetCollection(provider: EventProvider::class), ...])]`.
