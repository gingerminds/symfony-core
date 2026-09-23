# Coming from `gingerminds/laravel-core`

The bundle keeps the Laravel core logic: generic reusable CRUD, admin authentication,
roles/permissions administrable from the admin, API Platform on the same repositories,
filters/search/sort/facets, resource cache, generators. This page maps each concept.

## Concepts

| Laravel core | Symfony bundle | Notes |
|---|---|---|
| `LaravelCoreServiceProvider` | `GingermindsCoreBundle` (`AbstractBundle`) + `config/services.php` | Explicit service definitions, `gingerminds_core.*` ids. |
| `config/gingerminds-core.php` | `gingerminds_core:` config | `bin/console config:dump-reference gingerminds_core` |
| `ResourceResolver` | `ResourceRegistry` / `ResourceDefinition` | Also fed by `#[AsCrudController]`. |
| Eloquent model | Doctrine entity | Core entities: `Entity/<Type>/` holds the interface, the mapped superclass `Base*` and the concrete entity. |
| `ResourceModelInterface` | `ResourceInterface` | |
| `SortableModelInterface`, `SearchableModelInterface`, `FilterableModelInterface`, `EagerLoadableModelInterface` | `SortableInterface`, `SearchableInterface`, `FilterableInterface`, `EagerLoadableInterface` | Same contracts. Sort/filter keys are whitelisted against Doctrine metadata. |
| `CacheableResourceInterface`, `CacheCascadeInterface` | same names | Cache stores API responses, see [Cache](Cache.md). |
| timestamps | `TimestampableInterface`/`Trait` | Doctrine listener. Soft deletes are not ported: deletions are real. |
| `AbstractRepository::get(Request)` | `AbstractRepository::paginate(ListQuery)` | `ListQuery::fromRequest()`; repositories no longer read the request. |
| `AbstractRepository::update(FormRequest, Model)` | `save(entity, ?form)` + `beforeSave()` hook | Mapping is done by the form. |
| `FormRequest` (+ `FormRequestInterface`) | Symfony form type + validator constraints | One form for admin and API. |
| `Date/DecimalConverterTrait` | form types (`DateType`, `NumberType` with `scale`) | Conversion is a form concern. |
| `AbstractController` + generated per-resource CRUD | `AbstractCrudController` (generic) + `CrudRouteLoader` | A resource controller is usually empty. |
| `Route::resource()` | generated `{prefix}_index\|new\|edit\|delete` routes | `delete` is a POST with CSRF token. |
| Policies + `AbstractResourcePolicy` | Voters + `AbstractResourceVoter` (`VIEW`, `CREATE`, `EDIT`, `DELETE`) | Autoconfigured, no registration. |
| `Gate::before` Super-Admin | `SuperAdminVoter` | |
| `$user->can('edit users')` | `is_granted('edit users')` (`PermissionNameVoter`) | |
| `spatie/laravel-permission` | `Role`/`Permission` entities, `user_roles`, `role_permissions` | No direct user permissions (not used by the core). |
| `PermissionSeeder` | `gingerminds:permissions:sync` | Idempotent, derives resource permissions. |
| `spatie/laravel-model-states` + `select-state` | PHP backed enums + `select-enum` (`select-state` alias) | Symfony Workflow for transitions if needed. |
| Session guard + `AuthService` | `form_login`, `login_throttling`, `remember_me`, `AuthorizedDomainListener` | |
| `gingerminds-core.auth` + `EnsureAdminAreaIsAuthenticated` | firewall `access_control ^/admin` | |
| Sanctum | `access_token` authenticator + `ApiToken` entity | Hashed, revocable, optional TTL. |
| `LoginResponseEnricherInterface` (tag) | same, autoconfigured | |
| `AbstractApiProvider` / `BaseStateProcessor` | `ResourceProvider` / `ResourceProcessor` | Generic: core resources need no class. |
| `addFilters()` | `configureListQuery()` | |
| `ClassHierarchyResourceNameCollectionFactory` | not needed | An overridden bundle entity is excluded from the Doctrine mapping and API resources. |
| `ApiHeaderParameterRegistry` | `HeaderParameterRegistry` + `HeaderParameterProviderInterface` | |
| `EmbeddedResourceAttributesFixContextBuilder`, `ObjectNormalizer`/`PropertyAccessor` fixes | not needed | Eloquent-specific workarounds. |
| `JsonCollectionNormalizer` | removed | Use JSON-LD/Hydra: `totalItems` + `view` pagination links. |
| `FilterStore` subclasses + `AbstractInjectFiltersMiddleware` | `ComputedFilterStore` (keyed by resource class) + listener | |
| Blade layouts/components | Twig layouts/components (`@GingermindsCore/...`) | See [Layouts](templating/layouts.md). |
| Vite, jQuery, Select2, Livewire `SelectModel` | AssetMapper, Stimulus, UX Autocomplete (Tom Select), `/_autocomplete/{resource}` | No Node build. |
| `make:*` artisan commands | `make:gm:*` makers (MakerBundle) | See [Commands](Commands.md). |
| Migrations shipped by the package | `doctrine:migrations:diff` in the project | Symfony convention. |

## Behaviour changes worth knowing

- **API permissions**: core API operations check permissions (`VIEW`/`CREATE`/`EDIT`/`DELETE`);
  the Laravel core only required a token.
- **User deletion from the admin** requires `delete users` (the Laravel controller did not
  check it) and a user can never delete their own account.
- **API payloads**: field names follow the form (`isExternal`, `isDefault`, `roles` as role
  ids, `password: {first, second}`, `contributorId`/`contributorFirstname`...).
- **Number filter**: `0` is a valid bound (the Laravel handler ignored it).
- **Lists** always end their ordering with the identifier, so pagination is stable.
- **`itemsPerPage`** is capped by `AbstractRepository::$maxItemsPerPage` (200).
