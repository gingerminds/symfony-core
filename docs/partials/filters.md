# Filters

Implement `Gingerminds\CoreBundle\Model\FilterableInterface` and a static `getFilters()`:
each key is the `filters[<key>]` query parameter — a mapped field, an association name, or
a `relation.field` path — and each value configures how the filter is rendered in the admin
panel and applied by the repository (admin lists **and** API collections).

Common options: `type` (required), `label` (translation key), `disabled_for_back` (hide it
from the admin filters panel, API only).

Keys are resolved against the Doctrine metadata: an unknown field is ignored, never injected
into DQL.

## Date

```php
'publishedAt' => ['type' => 'date', 'label' => 'event.field.published_at'],
```

`filters[publishedAt][from]=2024-01-01&filters[publishedAt][to]=2024-12-31` (`Y-m-d`, both
optional, whole days inclusive).

## Number

```php
'price' => ['type' => 'number', 'label' => 'product.field.price'],
```

`filters[price][from]=10&filters[price][to]=100` (both optional, inclusive, `0` is a valid bound).

## Boolean

```php
'active' => ['type' => 'boolean', 'label' => 'product.field.active'],
```

`yes` / `no` (also `1`/`0`, `true`/`false`); `no` also matches `NULL`; `all` disables the filter.

## Select

```php
'type' => [
    'type' => 'select',
    'label' => 'event.field.type',
    'choices' => ['concert' => 'event.type.concert', 'festival' => 'event.type.festival'],
    'multiple' => true,
],
```

`filters[type]=concert` or `filters[type][]=concert&filters[type][]=festival`; `all` disables it.

## Select enum (replaces `select-state`)

For a field mapped with `enumType` (PHP backed enum — the Symfony replacement of
`spatie/laravel-model-states`):

```php
'status' => [
    'type' => 'select-enum',
    'label' => 'event.field.status',
    'choices' => array_combine(
        array_column(EventStatus::cases(), 'value'),
        array_map(static fn (EventStatus $s): string => 'event.status.' . $s->value, EventStatus::cases()),
    ),
    'multiple' => true,
],
```

Values are matched case-insensitively against the backing values **and** the case names
(`published`, `Published`, `PUBLISHED`); unresolved values are used as-is. `select-state` is
kept as an alias of this type.

## Select entity (replaces `select-model`)

```php
'category' => [
    'type' => 'select-entity',
    'label' => 'product.field.category',
    'entity' => Category::class,
    'resource' => 'category',   // optional: remote autocomplete through this resource
    'multiple' => true,
],
```

Filters on related ids through a to-one **or** to-many association (`filters[category]=3`,
`filters[tags][]=1&filters[tags][]=2`); `null` matches rows without relation, `all` disables
it. In the admin it renders a Tom Select (UX Autocomplete): remote search through
`/admin/_autocomplete/{resource}` when `resource` is set (the resource must be registered and
the entity `SearchableInterface`), otherwise a local list of the entities. `select-model` is
kept as an alias; the Livewire `SelectModel` component is replaced by the autocomplete endpoint.

## Custom filter types

```php
use Gingerminds\CoreBundle\Repository\Filter\AsFilterHandler;
use Gingerminds\CoreBundle\Repository\Filter\FilterHandlerInterface;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;

#[AsFilterHandler('geo-radius')]
final class GeoRadiusFilterHandler implements FilterHandlerInterface
{
    public function apply(QueryBuilderHelper $query, string $property, mixed $value, array $config): void
    {
        $field = $query->resolveField($property);   // DQL path or null if not mapped
        // $query->getQueryBuilder()->andWhere(...), $query->parameter($value), $query->join(...)
    }
}
```

Any bundle or project can register a type this way (or with the
`gingerminds_core.filter_handler` tag and its `type` attribute). Unregistered types are
silently ignored. To render it in the admin panel, add
`templates/bundles/GingermindsCoreBundle/components/list/filters/geo-radius.html.twig`
(see [Layouts](../templating/layouts.md)).

## See also

- [Resource model](../ResourceModel.md#optional-interfaces)
- [Facets](facets.md)
- [Sorting](../Sorting.md)
