# List parameters and filters

The API collections and the admin lists read the **same query parameters**, declared once by
attributes on the entity properties and applied by the **same API Platform filters**: the API
through its native Doctrine provider, the admin by applying the same parameters to its
repository query (`AbstractRepository::paginate()`, through API Platform's `ParameterExtension`).
`FilterParameters` builds them from the attributes; `FilterParametersResourceMetadataCollectionFactory`
adds them to every collection operation of a Doctrine entity resource (an operation declaring a
`QueryParameter` of the same key keeps its own), where API Platform documents (OpenAPI, Hydra)
and validates them.

| Parameter | Declared by | Filter |
|---|---|---|
| `page`, `itemsPerPage` | API Platform pagination (`paginationClientItemsPerPage: true`) | admin: repository `$itemsPerPage` (10) / `$maxItemsPerPage` (200) |
| `order[<path>]=asc\|desc` | `#[Sortable]` (see [Sorting](../Sorting.md)) | API Platform `SortFilter`, then the identifier |
| `search=text` | `#[Searchable]` | `SearchableFilter` |
| `<property>=...` | `#[Filter]` | by type, below |

The front passes **ids**, never IRIs (`category=5`, `tags[]=3`).

## `#[Filter]`

```php
use Gingerminds\CoreBundle\Model\Filter;

#[Filter(label: 'event.field.published_at')]                       // date (guessed)
private ?\DateTimeImmutable $publishedAt = null;

#[Filter(label: 'event.field.status', choices: ['draft' => 'Draft', 'published' => 'Published'], multiple: true)]
private EventStatus $status;                                       // enum (guessed)

#[Filter(label: 'event.field.category', resource: 'category')]     // relation, remote autocomplete
private ?Category $category = null;

#[Filter(type: Filter::PARTIAL, admin: false)]                     // API only
private string $code = '';
```

`type` is guessed from the Doctrine mapping when omitted: association → `relation`, enum column
→ `enum`, `boolean` → `boolean`, date / date-time → `date`, numeric → `number`, else `exact`.

| Type | Values | Filter |
|---|---|---|
| `exact` | `name=foo`, `name[]=foo&name[]=bar` | API Platform `ExactFilter` |
| `partial` | `name=fo` (case-insensitive `LIKE`) | API Platform `PartialSearchFilter` |
| `number` | `price[gte]=10&price[lte]=100` (also `gt`, `lt`, `ne`; `0` is a bound) | API Platform `ComparisonFilter(ExactFilter)` |
| `date` | `publishedAt[after]=2024-01-01&publishedAt[before]=2024-12-31`, `Y-m-d`, both optional and inclusive | `DisplayDateFilter` |
| `boolean` | `active=true\|false\|1\|0`, `false` includes NULL | `NullableBooleanFilter` |
| `enum` | `status=draft`, `status[]=draft&status[]=Archived` (value or case name) | `EnumFilter` |
| `relation` | `category=5`, `tags[]=3&tags[]=4`, `category=null` (none), combinable | `RelationFilter` |

Any other `type` is the service id of a filter in the API Platform filter locator (`api_platform.filter`
tag) or the class of a Doctrine ORM filter without constructor arguments; give it a `template`
for the admin panel.

Why some filters are the bundle's (`Gingerminds\CoreBundle\ApiPlatform\Filter\*`, all API Platform
`FilterInterface`s, QueryParameter only):

- `DisplayDateFilter`: whole days of the display timezone (`gingerminds_core.display_timezone`), the
  UTC bounds of those days on a date-time column (stored in UTC); API Platform's `DateFilter`
  compares the raw values;
- `NullableBooleanFilter`: `false` also matches NULL (a nullable boolean left empty is a "no");
- `EnumFilter`: the case names as well as the values (`BackedEnumFilter` only takes the values);
- `RelationFilter`: ids instead of IRIs (`IriFilter`), `null`, and a to-many association matched
  by a subquery (no joined collection, no duplicated row, a plain `LIMIT`);
- `SearchableFilter`: several paths OR-ed, the associations LEFT joined (`FreeTextQueryFilter` +
  `PartialSearchFilter` inner join them: a row without the relation would not be found).

## Invalid and empty values

Unknown parameters are ignored. The declared parameters carry their constraints (`FilterParameters`),
the same on both sides:

| Parameter | Valid values |
|---|---|
| `order[...]` | `asc`, `desc` (`ASC`, `DESC`) |
| `boolean` | `true`, `false`, `1`, `0` (case-insensitive), not a list |
| `number` | operators `gt`, `gte`, `lt`, `lte`, `ne` only, numeric bounds |
| `date` | operators `after`, `before` only, `Y-m-d` bounds |
| `enum` | a case value or name (case-insensitive), or a list of them |
| `relation` | an id, `null`, or a list of them (not an operator map) |

`exact`, `partial`, `search` and the custom filters are not constrained (give a custom filter's
`QueryParameter` its own `constraints`, or a JSON schema API Platform derives them from).

The API answers `422` on an invalid value (API Platform `ParameterValidatorProvider`, a violation
per parameter). The admin list ignores it (`FilterParameters::discardInvalid()`), shows a warning
flash naming the ignored parameters and marks the panel field (`is-invalid` and the message,
`filter_errors` in the template): an old bookmark still opens the list.

The admin drops the empty values of its panel (an empty field is not a filter); on the API an
empty value is a value (`name=` matches the empty names).

## The admin filters panel

`AbstractCrudController` passes `filter_configs` (the `#[Filter]` fields but `admin: false`, by
parameter: `type` (`select`, `text`, `number`, `date`, `boolean`, `entity`), `label`, `choices`,
`multiple`, `entity` (target class of a relation), `resource`, `template`), `filters` (the active
values by parameter) and `list_parameters` (the valued `Parameters`). A controller changes a field
by overriding `getCommonParameters()` (`$parameters['filter_configs']['status']['choices'] = ...`).
The panel fields are named after the parameters (`status[]`, `publishedAt[after]`...), the current
sort and page size are kept as hidden fields. `templates/components/list/filter/_<type>.html.twig`
render them; `template` replaces one.

Force a filter of an admin list by overriding `createListParameters()`:

```php
protected function createListParameters(Request $request): Parameters
{
    $parameters = parent::createListParameters($request);
    $parameters->get('site')?->setValue((string) $this->siteContext->id());

    return $parameters;
}
```

On the API, the context scope of every read (a site, a status) belongs in
`configureListQueryBuilder()` (the native providers start from `createListQueryBuilder()`), or in a
provider decorating the Doctrine one (see [Facets](facets.md)).
