# API

The API layer runs on API Platform 4 (Symfony integration, Doctrine ORM).

## Principle: native API Platform, the admin's repository

- **Reads** — the native API Platform Doctrine providers. They start from the repository's
  `createListQueryBuilder()` (the `repositoryMethod` of the operations' Doctrine state options,
  set for every entity whose repository is a bundle `RepositoryInterface`): the context scope of
  `configureListQueryBuilder()` and the `#[EagerLoad]` associations, collections and items alike.
  Collections take the list parameters of the entity attributes — `order[<path>]`, `search`, the
  `#[Filter]` properties, see [List parameters](partials/filters.md) — with the native pagination
  (`page`, `itemsPerPage`) and API Platform's eager loading; a sorted page ends with the
  identifier (`IdentifierOrderExtension`). The admin lists apply the same parameters.
- **Writes** — plain API Platform: the payload is **deserialized** into the entity (the
  `denormalizationContext` groups), **validated** by the entity constraints (`#[Assert\...]`,
  `#[UniqueEntity]`, validation groups in `validationContext`), then **persisted by the Doctrine
  processor** (`api_platform.doctrine.orm.state.persist_processor` / `remove_processor`). No form
  type, no processor to write. An invalid payload is a `422` with `violations`
  (`propertyPath`, `message`); a malformed one (unknown relation, wrong type) a `400`.
- **Repository hooks** — `RepositoryProcessor` decorates the two Doctrine processors: when the
  entity's repository is a bundle `RepositoryInterface`, the native write runs inside
  `saveWith()` / `removeWith()`, so `beforeSave()`, `afterSave()`, `beforeRemove()` and the
  transactions of the repository run exactly as for the admin (`save()` / `remove()`). Put the
  business logic shared by the admin and the API there (or in a Doctrine listener, like the
  password hashing), not in a processor.
- **Deletion rules** — a `Delete` with `validate: true` validates the `{resource}:delete` group
  (`BaseRole::GROUP_DELETE`: the Super-Admin role cannot be deleted, `422`); the admin checks the
  same group (`AbstractCrudController::getDeleteError()`).

### Relations: by id or by IRI

A relation is written by its **id** (`3` or `"3"`) or by its **IRI** (`"/api/roles/3"`), to one
or to many; a nested document (an object) creates the related entity when the property's groups
allow it (the contributor of a user). An unknown id is a `400`, like an unknown IRI.
`RelationIdDenormalizer` turns the ids of the relations of a resource payload into IRIs of the
target resource (the project entity when the bundle one is overridden), then the native
denormalizer takes over: nothing to declare per entity.

Reads embed the related objects with their `id` (and the JSON-LD `@id`): a client never needs
to parse an IRI.

```http
POST /api/users
{
    "email": "ada@example.com",
    "plainPassword": "secret-password",
    "roles": [3, "4", "/api/roles/5"],
    "contributor": {"firstname": "Ada", "lastname": "Lovelace"}
}
```

```http
PATCH /api/users/12                       (Content-Type: application/merge-patch+json)
{"contributor": 7}                        → links the existing contributor 7, the previous one is kept without user
{"contributor": {"lastname": "Byron"}}    → merged into the current contributor (JSON Merge Patch)
```

A new contributor replacing the current one is created on its own resource:
`POST /api/contributors {"firstname": "Ada", "lastname": "Lovelace", "user": 12}`.

The plain password is write only (`plainPassword`, required on `POST` by the `user:create`
validation group), hashed on flush by `UserPasswordListener`. The admin form keeps its
confirmation field (`RepeatedType`); the API takes the password once.

## Wiring a resource

`bin/console make:gm:resource Catalog/Product --api` generates everything. By hand, an
`#[ApiResource]` on the entity is all it takes (no provider, no processor):

```php
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['product:list']],
            security: "is_granted('VIEW', 'product')",
        ),
        new Get(security: "is_granted('VIEW', object)"),
        new Post(security: "is_granted('CREATE', 'product')"),
        new Patch(security: "is_granted('EDIT', object)"),
        new Delete(security: "is_granted('DELETE', object)"),
    ],
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:edit']],
    paginationClientItemsPerPage: true,
)]
class Product implements ResourceInterface { /* ... */ }
```

Everything shared by the operations (serialization contexts) is declared once on
`#[ApiResource]` and inherited by every operation; an operation only overrides what differs
(`security`, the list group of `GetCollection`, the groups of a creation that differs from an
edition: `denormalizationContext` + `validationContext` on `Post`, see `User`).

A rule specific to the API (an input DTO, a side effect after the write) is a processor
decorating the native one, declared with `processor:` on the operation; it stays thin and calls
the decorated processor.

> Change from the Laravel core: core resources declare `security` expressions on every
> operation (the Laravel core only required a Sanctum token, whatever the permissions).
> The API is open by default: an operation is only closed by its `security` expression, see
> [Authentication](Authentication.md#protecting-api-operations).

### Nested endpoints and forced filters

A nested endpoint (`/api/properties/{property}/rooms`) declares its `uriVariables` link
(`new Link(fromClass: Property::class, toProperty: 'property')`): the native Doctrine provider
joins it. A filter forced on a collection (the published items only) is a provider decorating the
native one that sets the parameter's value (see [Facets](partials/facets.md)), or a condition of
`configureListQueryBuilder()` when every read needs it.

## Formats and pagination

Use **JSON-LD** (`Accept: application/ld+json`, API Platform's default): collections carry
`totalItems`, a `view` with the `first`/`last`/`previous`/`next` page links, a `search` template
of the list parameters, and the computed filters (`filters`, see [Facets](partials/facets.md)).
With plain `application/json`, collections are bare arrays without pagination metadata nor
computed filters. The OpenAPI document (`/api/docs`) lists every list parameter.

## Overriding a bundle resource's `#[ApiResource]`

A project entity overriding a bundle entity (see
[Configuration](Configuration.md#entity)) **inherits the bundle `#[ApiResource]`**: no need to
restate it. The bundle entity is replaced by the project one among the API resources
(`CoreResourceNameCollectionFactory`), which gets the operations, groups, `security` and
`validationContext` of the bundle declaration (`OverriddenEntityResourceMetadataCollectionFactory`,
right above the attributes reading: the filters, parameters and Doctrine providers then apply
to the project class). An added property is exposed by its serialization groups:

```php
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User extends BaseUser
{
    #[ORM\Column(nullable: true)]
    #[Groups([BaseUser::GROUP_READ, BaseUser::GROUP_CREATE, BaseUser::GROUP_EDIT])]
    private ?string $phone = null;
}
```

An `#[ApiResource]` on the project entity **replaces** the bundle one (no merge); a read-only
exposure:

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
    paginationClientItemsPerPage: true,
)]
class User extends BaseUser {}
```

Diff the operations you restate (pagination options, `security`...) against the bundle ones:
nothing errors when you silently drop one.

To stop exposing a bundle resource, overridden or not:

```yaml
gingerminds_core:
    resources:
        permission:
            api: false
```

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
