# Resource model

A resource is the combination of an **entity**, its **repository**, its **form type**, a
**voter**, and optionally a **CRUD controller** (admin) and an **`#[ApiResource]`**.
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

### Optional interfaces and attributes

All of them live under `Gingerminds\CoreBundle\Model`.

| Interface / attribute | Effect |
|---|---|
| `#[Sortable]` (property attribute) | Enables `order[<path>]=asc\|desc` (admin column headers and API) on that property, or on `relation.field` with `#[Sortable(paths: ['field'])]` on a to-one association; any other path is ignored. See [Sorting](Sorting.md). |
| `#[Searchable]` (property attribute) | Enables `search=` (and the admin search field): case-insensitive `LIKE` on the marked properties, or on `relation.field` with `#[Searchable(paths: ['field'])]` on an association; any other field is not searched. `SearchableFields::of(Product::class)` returns the paths (parent classes included). |
| `#[Filter]` (property attribute) | A list parameter named after the property (`status=draft`, `price[gte]=10`, `publishedAt[after]=2024-01-01`, `category=5`) on the API collections and the admin lists, with its admin panel field (`label`, `choices`, `multiple`, `resource`; `admin: false` for the API only). See [List parameters](partials/filters.md) and [Facets](partials/facets.md). |
| `#[EagerLoad]` (property attribute) | The marked associations (`#[EagerLoad(paths: ['language'])]` on `$translations` also loads `translations.language`; parents and traits included, `EagerLoads::of()`) are loaded with the entities on every repository read (lists, API reads, admin edit page). Fixes N+1 at the source. To-one paths are fetch-joined; on paginated lists, a path going through a collection is loaded after the page, one `WHERE IN` query per path (joining it would paginate through `DISTINCT` subqueries over the whole table). |
| `#[Cacheable(key: 'product')]` (class attribute) | Caches the API responses of the resource (`ttl` in seconds, default `gingerminds_core.cache.default_ttl`). See [Cache](Cache.md). |
| `#[CacheCascade(['product'])]` (class attribute) | Invalidates the cache of the given `#[Cacheable]` keys when this entity changes. See [Cache](Cache.md). |
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
| `paginate(Parameters $parameters, int $page = 1, ?int $itemsPerPage = null): Paginator` | Admin lists: the list query with the valued list parameters (`FilterParameters::fromQuery()`) applied by API Platform's `ParameterExtension`, as on the API, then the identifier order. The total is a separate `COUNT` without eager loads nor sort (`countForList()`, skipped on a partial page); the id subqueries (`DISTINCT`) only run when a collection is joined. |
| `createListQueryBuilder(): QueryBuilder` | The query every read starts from: `configureListQueryBuilder()` and the `#[EagerLoad]` joins, alias `o`. The API Platform Doctrine providers use it (`repositoryMethod`). |
| `applyParameters(QueryBuilder $qb, Parameters $parameters)` | Applies valued list parameters to a query of the entity. |
| `findOneForRead(int\|string $id): ?object` | Single item of the list query (admin edit page). |
| `save(object $entity, bool $flush = true)` | Write entry point (admin, API, commands). |
| `remove(object $entity, bool $flush = true)` | Delete entry point. |

Hooks to override:

| Hook | Use |
|---|---|
| `configureListQueryBuilder(QueryBuilder $qb)` | Joins/conditions every read needs (a scope, a context such as the current site): admin, API collections and items, facets. |
| `beforeSave(object $entity)` | Write-side business logic on the entity — the Laravel `update()` method. The input that is not an entity field (unmapped form fields) is handled by the form type, in a form event listener (see `UserType::submitContributor()`); a hash or a value derived on every write belongs in a Doctrine listener (see `UserPasswordListener`). |
| `afterSave(object $entity)`, `beforeRemove(object $entity)` | Side effects. |

Properties: `$itemsPerPage` (default 10), `$maxItemsPerPage` (default 200, caps `itemsPerPage`).

### List parameters

The list parameters are API Platform `Parameters` (`ApiPlatform\Metadata\Parameters`) built by
`FilterParameters` from the entity attributes, valued from a query string array. Repositories
never read the HTTP request, so they can be used from commands, messages or tests:

```php
$repository->paginate(
    $filterParameters->fromQuery(Product::class, ['status' => 'published', 'order' => ['publishedAt' => 'desc']]),
    itemsPerPage: 50,
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

## Access

No voter to write: the core `ResourceVoter` checks `view|edit|delete products` for every
registered resource (`is_granted('EDIT', product)`, `is_granted('CREATE', 'product')`). See
[User → Resource voter](User.md#resource-voter-laravel-policies) for other permissions and
specific rules.

## CRUD controller

```php
#[AsCrudController(resource: 'product', entity: Product::class, form: ProductType::class, translationDomain: 'admin')]
final class ProductController extends AbstractCrudController
{
}
```

Routes (`admin_product_index|new|edit|delete`, `/admin/products[/new|/{id}/edit|/{id}/delete]`):
the `#[Route]` attributes of `AbstractCrudController`, relative to the resource. The core route
loader (`gingerminds_crud`, imported by `@GingermindsCoreBundle/config/routes.php`) loads them for
each resource under `/{admin_prefix}/{path}`, names them `{route_prefix}_{name}` and sets the
`_resource` default the controller reads. Your own actions are declared the same way and get the
same prefixes:

```php
#[Route('/export', name: 'export', methods: ['GET'])]           // admin_product_export, /admin/products/export
#[IsGranted('VIEW', subject: new Expression(self::RESOURCE_SUBJECT))]
public function export(): Response
```

The resource path and route prefix are not on the controller: they stay configurable
(`resources.*.path`, `route_prefix`, `controller`, see [Configuration](Configuration.md)) and one
controller class can serve several resources. The core keeps the CRUD controllers out of your
`routing.controllers` import (no duplicate, unprefixed routes). Overriding a CRUD action in a
subclass drops its attributes (PHP does not inherit them): restate `#[Route]` and `#[IsGranted]`
(the route loader refuses an override that does not, rather than lose its route or access check):

```php
#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
#[IsGranted('CREATE', subject: new Expression(self::RESOURCE_SUBJECT))]
public function new(Request $request): Response
{
    // ...
    return parent::new($request);
}
```

Templates and hooks: see [Layouts](templating/layouts.md). Useful hooks: `createEntity()`, `getFormOptions()`,
`getIndexParameters()`, `getFormParameters()`, `getDeleteError()` (by default the first violation of
the `{resource}:delete` validation group, shared with the API `Delete`), `redirectAfterSave()`,
`createListParameters()` (force a filter on the admin list). The redirect after a save is
configurable without code: see [Redirect after save](Configuration.md#redirect-after-save).

### Nested resources

A resource under a parent puts the parent id in its path, e.g. the products of a category:

```php
#[AsCrudController(resource: 'category_product', entity: Product::class, path: 'categories/{categoryId}/products')]
final class CategoryProductController extends AbstractCrudController
```

The route loader serves `/admin/categories/{categoryId}/products[/new|/{id}/edit|/{id}/delete]`.
`getRouteParameters()` returns the parameters of the current route but `id` (`['categoryId' => '7']`):
the redirects after save and delete keep them, and the templates get them as `route_parameters`
(list, tree, form and row action links). Filter the list on the parent in the repository or with
`createListParameters()` and set it on new entities in `createEntity()`. The entity of `edit()` and
`delete()` is loaded by `ResourceEntityValueResolver` (the repository's `findOneForRead()`, 404
when missing), then must belong to the route: `entityBelongsToRoute(object $entity, array
$routeParameters): bool` returning false is a 404, checked before `#[IsGranted]` and
`#[IsCsrfTokenValid]` (`ResourceEntityRouteListener`) for every argument of that resolver,
including your own actions. By default, a `{xxxId}` parameter naming a to-one association `xxx` of
the entity must be its identifier: `categories/{categoryId}/products` refuses a product whose
`category` is another one, with no code. Override it when the association is named otherwise or
for another rule:

```php
public function entityBelongsToRoute(object $entity, array $routeParameters): bool
{
    // The menu of the route must be one of the current site (getMenu() throws a 404 otherwise).
    return $entity instanceof MenuItemInterface
        && $entity->getMenu() === $this->getMenu()
        && parent::entityBelongsToRoute($entity, $routeParameters);
}
```

### Controller services

`AbstractCrudController` extends Symfony's `AbstractController`: `render()`, `redirectToRoute()`,
`addFlash()`, `createForm()`, `isGranted()`... are the framework's. Access is checked by
attributes on the actions: `#[IsGranted('VIEW'|'CREATE', subject: <the route's _resource>)]`,
`#[IsGranted('EDIT'|'DELETE', subject: 'entity')]` and `#[IsCsrfTokenValid('delete-{id}')]` on
`delete()` (an invalid token is an `InvalidCsrfTokenException`: on an HTML request of a resource
route, `InvalidCsrfTokenListener` answers it with the `flash.invalid_csrf` flash and a redirection
to the referer of the same host, or else to the list, instead of the firewall's login page). It
subscribes to the resource registry, Doctrine and the translator (`getResourceRegistry()`,
`getDoctrine()`, `trans()`); any other service goes in the constructor of your controller,
autowired (the parent has none):

```php
#[AsCrudController(resource: 'category_product', entity: Product::class, path: 'categories/{categoryId}/products')]
final class CategoryProductController extends AbstractCrudController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }
}
```

## List performance

`paginate()` keeps lists cheap on large tables by itself (plain `COUNT`, `LIMIT` without
`DISTINCT` subqueries, collection eager loads loaded after the page). What depends on the
resource:

1. **Eager load what the list shows.** Every relation displayed by the admin list or exposed in
   the API list group is marked `#[EagerLoad]`, otherwise each row
   runs its own query. The inverse side of a one-to-one (`mappedBy`) is never lazy: it costs one
   query per row even when not displayed.
2. **Index the sorts and filters.** Add a `#[ORM\Index]` on the default sort of the repository
   and on the sortable / filtered columns once the table can grow (then `make:migration`).
3. **Search is a full scan.** `search=` runs `LOWER(field) LIKE '%…%'`, which no index can
   serve: past a few hundred thousand rows, move to a full-text search.

## See also

- [Configuration](Configuration.md) — registering/overriding resources.
- [API](API.md) — exposing the resource through API Platform.
- [Filters](partials/filters.md), [Facets](partials/facets.md), [Sorting](Sorting.md).
