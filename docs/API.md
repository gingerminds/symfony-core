# API

The API layer runs on API Platform 4 (Symfony integration, Doctrine ORM).

## Principle: one repository, one form

Like the Laravel core, API reads and writes go through the **same repository and the same
validation as the admin**:

- **Reads** — `Gingerminds\CoreBundle\ApiPlatform\State\ResourceProvider` calls
  `RepositoryInterface::paginate()` / `findOneForRead()` with a `ListQuery` built from the
  request: pagination (`page`, `itemsPerPage`), `sortBy`/`sort`, `filters[...]`,
  `filters[search]`, eager loads. The API Platform Doctrine filters are not needed.
- **Writes** — `Gingerminds\CoreBundle\ApiPlatform\State\ResourceProcessor` submits the JSON
  payload to the resource **form type** (POST/PUT with `clearMissing`, PATCH partial) and
  calls `RepositoryInterface::save()` (so `beforeSave()` business logic runs). Form errors
  become a standard `422` with `violations`. DELETE calls `RepositoryInterface::remove()`.

Operations must declare `deserialize: false` (the form maps the payload, not the serializer).

## Wiring a resource

`bin/console make:gm:resource Catalog/Product --api` generates everything. By hand:

```php
namespace App\State\Catalog;

/**
 * @extends ResourceProvider<Product>
 */
final class ProductProvider extends ResourceProvider
{
    public function __construct(ProductRepository $repository, RequestStack $requestStack)
    {
        parent::__construct($repository, $requestStack);
    }
}

/**
 * @extends ResourceProcessor<Product>
 */
final class ProductProcessor extends ResourceProcessor
{
    public function __construct(ProductRepository $repository, FormFactoryInterface $formFactory, RequestStack $requestStack)
    {
        parent::__construct($repository, $formFactory, $requestStack, ProductType::class);
    }
}
```

```php
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['product:list']],
            security: "is_granted('VIEW', 'product')",
        ),
        new Get(security: "is_granted('VIEW', object)"),
        new Post(security: "is_granted('CREATE', 'product')", deserialize: false),
        new Patch(security: "is_granted('EDIT', object)", deserialize: false),
        new Delete(security: "is_granted('DELETE', object)"),
    ],
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:edit']],
    provider: ProductProvider::class,
    processor: ProductProcessor::class,
    paginationClientItemsPerPage: true,
)]
class Product implements ResourceInterface { /* ... */ }
```

Everything shared by the operations (provider, processor, serialization contexts) is declared
once on `#[ApiResource]` and inherited by every operation; an operation only overrides what
differs (`security`, the list group of `GetCollection`). `deserialize` is an operation-level
option in API Platform, hence its repetition on `Post`/`Patch`.

> Change from the Laravel core: core resources declare `security` expressions on every
> operation (the Laravel core only required a Sanctum token, whatever the permissions).

### Mapping URI variables to filters

For nested endpoints (`/api/properties/{property}/rooms`), turn the URI variable into a
filter in the provider (the Laravel `addFilters()`), then declare the filter on the entity
(`getFilters()`):

```php
protected function configureListQuery(ListQuery $query, Operation $operation, array $uriVariables, array $context): ListQuery
{
    return isset($uriVariables['property']) ? $query->withFilter('property', $uriVariables['property']) : $query;
}
```

## Formats and pagination

Use **JSON-LD** (`Accept: application/ld+json`, API Platform's default): collections carry
`totalItems` and a `view` with the `first`/`last`/`previous`/`next` page links. With plain
`application/json`, collections are bare arrays without pagination metadata.

## Overriding a bundle resource's `#[ApiResource]`

Class attributes are not inherited, so a project entity overriding `User` (see
[Configuration](Configuration.md#overriding-a-built-in-resource)) declares its own
`#[ApiResource]` — or none, to stop exposing it. Because the bundle excludes its own entity from
the Doctrine mapping and the API resources as soon as it is overridden, there is never a duplicate resource (no equivalent of the
Laravel `ClassHierarchyResourceNameCollectionFactory` is needed).

Serialization groups, on the other hand, are declared on the base class **properties** and are
inherited. Reuse the bundle services as provider/processor:

```php
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => [BaseUser::GROUP_LIST]],
            security: "is_granted('VIEW', 'user')",
        ),
        new Get(security: "is_granted('VIEW', object)"),
        // no Post/Patch/Delete: read-only
    ],
    normalizationContext: ['groups' => [BaseUser::GROUP_READ]],
    provider: 'gingerminds_core.api.provider.user',
    paginationClientItemsPerPage: true,
)]
class User extends BaseUser {}
```

As in the Laravel core: diff the operations you override (pagination options, `security`...)
instead of skimming them — nothing errors when you silently drop one.

## Documenting context headers (`X-Site-Id`, `Accept-Language`...)

Implement `Gingerminds\CoreBundle\ApiPlatform\Metadata\HeaderParameterProviderInterface`
(autoconfigured): every operation of every resource using the marker trait (anywhere in its
class hierarchy) or implementing the marker interface gets the header documented in OpenAPI.

```php
final class SiteHeaderParameterProvider implements HeaderParameterProviderInterface
{
    public function getMarker(): string
    {
        return SiteContextedTrait::class;
    }

    public function getHeaderParameter(): HeaderParameter
    {
        return new HeaderParameter(key: 'X-Site-Id', description: 'Restricts the response to the given site.', schema: ['type' => 'string']);
    }
}
```

The registry (`gingerminds_core.api.header_parameter_registry`) also has a `register()`
method for runtime registration.

## Computed properties

Getters with `#[Groups]` are serialized and typed from their return type
(`BaseRole::getPermissionsCount(): int`): no `nativeType` is needed, unlike Eloquent accessors.

## See also

- [Resource model](ResourceModel.md)
- [Filters](partials/filters.md) and [Facets](partials/facets.md)
- [Cache](Cache.md)
- [Authentication](Authentication.md#api-authentication-sanctum-equivalent)
