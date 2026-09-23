# Resource model

A resource is the combination of an **entity**, its **repository**, its **form type**, a
**voter**, and optionally a **CRUD controller** (admin) and an **API provider/processor**.
`bin/console make:gm:resource Namespace/Name [--api]` generates all of it (see [Commands](Commands.md)).

## Entity

```php
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Model\ResourceInterface;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
class Product implements ResourceInterface, \Stringable
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    // __toString() is used for labels (flash messages, autocomplete, filters)
}
```

### Optional interfaces

All of them live under `Gingerminds\CoreBundle\Model`.

| Interface | Effect |
|---|---|
| `SortableInterface` | Enables `?sortBy=&sort=asc\|desc` (admin column headers and API). `sortBy` accepts a mapped field or `relation.field` through a to-one association; anything else is ignored (whitelisted against the Doctrine metadata, never injected in DQL). See [Sorting](Sorting.md). |
| `SearchableInterface` | Enables `filters[search]`: case-insensitive `LIKE` on `getSearchableFields()` (supports `relation.field`). |
| `FilterableInterface` | Enables the filters panel and `filters[...]` on the API, from `getFilters()`. See [Filters](partials/filters.md) and [Facets](partials/facets.md). |
| `EagerLoadableInterface` | `getEagerLoads()` associations are fetch-joined on every repository read (lists, API reads, admin edit page). Fixes N+1 at the source. |
| `CacheableResourceInterface` | Caches the API responses of the resource. See [Cache](Cache.md). |
| `CacheCascadeInterface` | Invalidates other resources' cache when this entity changes. See [Cache](Cache.md). |
| `TimestampableInterface` + `TimestampableTrait` | `createdAt`/`updatedAt` filled automatically (Eloquent timestamps). |

## Repository

```php
use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Repository\AbstractRepository;

/**
 * @extends AbstractRepository<Product>
 */
class ProductRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }
}
```

That is all: pagination, sorting, search, filters and eager loads come from `AbstractRepository`.

| Method | Purpose |
|---|---|
| `paginate(ListQuery $query): Paginator` | Admin lists and API collections. |
| `findForList(ListQuery $query): array` | Same query, no pagination. |
| `createListQueryBuilder(ListQuery $query, array $excludedFilters = []): QueryBuilder` | The underlying query, to build on. |
| `findOneForRead(int\|string $id, ?ListQuery $query = null): ?object` | Single item honouring context filters and eager loads (API item reads, admin edit page). |
| `save(object $entity, ?FormInterface $form = null, bool $flush = true)` | Write entry point (admin + API). |
| `remove(object $entity, bool $flush = true)` | Delete entry point. |

Hooks to override:

| Hook | Use |
|---|---|
| `configureListQueryBuilder(QueryBuilder $qb, ListQuery $query)` | Joins/conditions every read needs (a scope, a context such as the current site). |
| `beforeSave(object $entity, ?FormInterface $form)` | Write-side business logic — the Laravel `update()` method. Unmapped form fields are read from `$form`. |
| `afterSave(...)`, `beforeRemove(...)` | Side effects. |

Properties: `$itemsPerPage` (default 10), `$maxItemsPerPage` (default 200, caps `itemsPerPage`).

### ListQuery

`Gingerminds\CoreBundle\Repository\ListQuery` is an immutable value object built from the
request (`ListQuery::fromRequest($request)`): `page`, `itemsPerPage`, `sortBy`, `sort`,
`filters`. Repositories never read the HTTP request, so they can be used from commands,
messages or tests:

```php
$repository->paginate(
    (new ListQuery(itemsPerPage: 50))
        ->withFilter('status', 'published')
        ->withSort('publishedAt', 'desc'),
);
```

## Form type

A plain Symfony form type, used by **both** the admin and the API (the Laravel `FormRequest`):

```php
class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, ['label' => 'product.field.name', 'size' => 'md']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Product::class, 'translation_domain' => 'admin']);
    }
}
```

Validation goes on the entity (`#[Assert\...]`) or on the fields (`constraints`). The `size`
option (`tiny`, `sm`, `md`, `lg`, `xl`) lays the field out on the admin grid.

## Voter

```php
final class ProductVoter extends AbstractResourceVoter
{
    protected function getResourceName(): string { return 'product'; }
    protected function getSubjectClass(): string { return Product::class; }
    protected function getPermissionName(): string { return 'products'; }
}
```

See [User → Roles & permissions](User.md#roles--permissions).

## CRUD controller

```php
#[AsCrudController(resource: 'product', entity: Product::class, form: ProductType::class, translationDomain: 'admin')]
final class ProductController extends AbstractCrudController
{
}
```

Generated routes (`admin_product_index|new|edit|delete`), templates and hooks: see
[Layouts](templating/layouts.md). Useful hooks: `createEntity()`, `getFormOptions()`,
`getIndexParameters()`, `getFormParameters()`, `getDeleteError()`, `redirectAfterSave()`,
`createListQuery()` (force a filter on the admin list).

## See also

- [Configuration](Configuration.md) — registering/overriding resources.
- [API](API.md) — exposing the resource through API Platform.
- [Filters](partials/filters.md), [Facets](partials/facets.md), [Sorting](Sorting.md).
