# Changelog

All notable changes to this bundle are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Admin trees: `TreeNodeInterface`, `TreeNodes` (group by parent, flatten, parent choices),
  `TreeRepositoryInterface` / `TreeRepositoryTrait` (`findTreeNodes()`, `reorderLevel()`,
  `findIdsWithDescendants()`, a new or moved node at the end of its level, `$scope` criteria for
  several trees in a table), `AbstractTreeCrudController` (tree index, `new?parent_id=`,
  `reorder` with the EDIT permission and the `X-CSRF-Token` header), the `#[NoTreeCycle]`
  constraint and the `components/list/_tree.html.twig` partial. They replace the copies of the
  cms (`AbstractTreeCrudController`, page categories, menu items) and the media manager (media
  categories).

### Changed

- **BC break** Bundles: `AbstractResourceBundle` holds what each bundle class repeated (resources
  and overridable entities, `<alias>.resource.<name>.entity` parameters, `OverriddenEntityPass`,
  Doctrine mappings, `resolve_target_entities`, `resolveConfig()`) and
  `extensionConfigValue()` reads another extension's raw configuration. `GingermindsCoreBundle`
  extends it; `CORE_RESOURCES` is renamed `RESOURCES`. The bundle resources reach the registry
  through `ResourceRegistryPass::registerBundleResource()`, below the `#[AsCrudController]`
  attributes and the `gingerminds_core.resources` configuration (a bundle no longer prepends them
  to `gingerminds_core.resources`). `RepositoryParameterExtensionPass` gives every
  `AbstractRepository` service the `ParameterExtension` (no more `setParameterExtension()` call in
  the service definitions).
- API: the `search` parameter is in the Hydra `search` template of the collections, mapped to
  `hydra:freetextQuery` (its `property`; `SearchableFilter` searches its `properties`).
- **BC break** List parameters: one set of rules for the API and the admin. `FilterParameters`
  gives each parameter its constraints (`ParameterValue`: the value shape, scalar, list or
  operator map, and each item): sort direction, `boolean` (`true|false|1|0`), `number` (numeric
  `gt|gte|lt|lte|ne` bounds), `date` (`Y-m-d` `after|before` bounds), `enum` (case value or
  name), `relation` (ids or `null`). The API answers `422` on these invalid values, which were
  ignored (`active=all` was all the rows). The admin lists validate with the same constraints
  (`FilterParameters::discardInvalid()`): the invalid parameters are ignored, named in a warning
  flash (`list.invalid_parameters`) and their panel field is marked (`filter_errors`).
  `FilterParameters` takes the validator.
- **BC break** Autocomplete: the `gingerminds_core_autocomplete` endpoint
  (`/_autocomplete/{alias}` under the admin prefix) is UX Autocomplete's controller. Each
  registered resource gets a `ResourceAutocompleter` (`EntityAutocompleterInterface`, alias: the
  resource name, declared by `ResourceRegistryPass`): the `search` filter of the lists on the list
  query without its eager loads (`createScopedQueryBuilder()`, now public and part of
  `RepositoryInterface`), 20 results a page, the VIEW permission of the resource. The route
  parameter is `alias` (was `resource`); the project's own `#[AsEntityAutocompleteField]` types
  are served by the same endpoint, behind the admin firewall. `AutocompleteController` and
  `ResourceDefinitionValueResolver` (its only user) are removed.
- **BC break** Computed filters: `InjectComputedFiltersListener` (which decoded and re-encoded every
  collection response) is replaced by `ComputedFiltersCollectionNormalizer`, a decorator of the
  Hydra collection normalizer adding the `ComputedFilterStore` entries as `filters` while the
  collection is serialized. Only JSON-LD collections carry them: a plain `application/json`
  collection stays a bare list (it was wrapped as `{member, filters}`).
- **BC break** Lists: one engine, the API Platform filters, for the API collections and the admin
  lists, with the same query parameters. Before → after: `page`, `itemsPerPage` (unchanged);
  `sortBy=x&sort=desc` → `order[x]=desc` (API Platform `SortFilter`, `#[Sortable]` paths only, a
  wrong direction is a `422` on the API); `filters[search]=` → `search=`; `filters[x]=` → `x=`;
  number `filters[x][from|to]` → `x[gte|lte]` (also `gt`, `lt`, `ne`); date `filters[x][from|to]`
  → `x[after|before]` (days of the display timezone); boolean `yes|no|all` → `true|false|1|0`
  (`false` still matches NULL, any other value is ignored); `all` is no longer a value (an empty
  admin field is not a filter); relations by id unchanged (`category=5`, `tags[]=3`,
  `category=null`). `FilterableInterface::getFilters()` is replaced by the `#[Filter]` property
  attribute, which declares both the filter (`type`: `exact`, `partial`, `number`, `date`,
  `boolean`, `enum`, `relation`, guessed from the Doctrine mapping, or an API Platform filter
  service id / class) and the admin panel field (`label`, `choices`, `multiple`, `resource`,
  `template`; `admin: false` replaces `disabled_for_back`; `entity` is read from the mapping;
  `select-model` / `select-state` aliases are gone). `FilterParameters` builds the
  `QueryParameter`s from `#[Sortable]`, `#[Searchable]` and `#[Filter]`, and
  `FilterParametersResourceMetadataCollectionFactory` adds them to every collection operation of a
  Doctrine entity resource (OpenAPI, Hydra `search`, validation by API Platform). Custom filters,
  all API Platform `FilterInterface`s: `DisplayDateFilter`, `NullableBooleanFilter`, `EnumFilter`
  (value or case name), `RelationFilter` (ids, `null`, to-many by subquery) and `SearchableFilter`
  (OR of LIKEs, associations LEFT joined); `exact`, `partial`, `number` and the sorts use
  `ExactFilter`, `PartialSearchFilter`, `ComparisonFilter` and `SortFilter`.
  `ListQuery`, `FilterHandlerRegistry`, `FilterHandlerInterface`, `AsFilterHandler`, the six
  handlers, the `gingerminds_core.filter_handler` tag and `ResourceProvider` (and the
  `gingerminds_core.api.provider.*` services) are removed: the API uses the native Doctrine
  providers, starting from the repository's `createListQueryBuilder()` (the `repositoryMethod` of
  the Doctrine state options, set automatically when the repository is a `RepositoryInterface`:
  `configureListQueryBuilder()` scope and `#[EagerLoad]` joins), with the native pagination (the
  API Platform default page size, not the repository's) and a final order by identifier
  (`IdentifierOrderExtension`). Repository: `paginate(Parameters $parameters, int $page = 1,
  ?int $itemsPerPage = null)`, `createListQueryBuilder()` without argument,
  `applyParameters(QueryBuilder, Parameters)`, `findOneForRead(int|string $id)`,
  `configureListQueryBuilder(QueryBuilder $qb)`, `countForList(Parameters)`;
  `findForList()` and `setFilterHandlerRegistry()` are removed, `setParameterExtension()`
  (autowired) replaces the latter. Facets (`AbstractFacetRepository`, `AbstractFilterComputeService`)
  take the valued `Parameters` (the collection operation's on the API) instead of a `ListQuery`;
  `AbstractFilterComputeService::getEntityClass()` and its template are gone, a computed date
  filter's `type` is always `date`. Admin: `AbstractCrudController::createListQuery()` becomes
  `createListParameters(Request): Parameters`; the index template gets `list_parameters` instead
  of `list_query`, `filters` holds the active values by parameter, `filter_configs` the
  `#[Filter]` panel fields (`type`: `select`, `text`, `number`, `date`, `boolean`, `entity`; the
  `_select_entity` partial is renamed `_entity`); `gm_list_path(route, overrides, routeParameters)`,
  `gm_sort_path(route, property, routeParameters)`, `gm_sort_direction(property)` and the
  `sort_header(column, route, ...)` macro read the current request (no `ListQuery` argument).
  `make:gm:api` no longer generates a provider (it prints the `#[ApiResource]`), the
  `Provider.tpl.php` skeleton and `ResourceName::providerClass()` are removed.
- **BC break** Sorting: `SortableInterface` is replaced by the `#[Sortable]` property attribute
  (`#[Sortable(paths: ['name'])]` on a to-one association). `?sortBy=` only accepts the marked
  paths, no longer any mapped field (`password`) or join; the admin column headers link the
  `#[Sortable]` properties (`sortable_fields` template variable instead of `is_sortable`,
  `sortable: false` to opt a column out). `RoleRepository::save()` runs in a transaction: a
  failed flush keeps the previous default role.
- **BC break** Search: `SearchableInterface::getSearchableFields()` is replaced by the
  `#[Searchable]` property attribute (`#[Searchable(paths: ['name'])]` on an association searches
  `relation.name`), read by `SearchableFields`. `filters[search]` only matches the marked
  properties; `is_searchable` (admin search field) is true when the entity has at least one; the
  generated entities no longer implement `SearchableInterface`.
- **BC break** Eager loads: `EagerLoadableInterface::getEagerLoads()` is replaced by the
  `#[EagerLoad]` attribute on the associations (`#[EagerLoad(paths: ['language'])]` on
  `$translations` for `translations.language`), read by `EagerLoads` (parents and traits
  included). The `eager_loads` variable of the `Entity.tpl.php` maker skeleton is removed: a
  trait added through `traits` marks its own associations.
- **BC break** Twig: `GingermindsCoreExtension` (built with all its dependencies on every
  render) is split into `ResourceExtension`, `AdminExtension` and `ListExtension`, plain services
  whose methods carry `#[AsTwigFunction]`: Twig runtimes, instantiated on the first call of one of
  their functions. The `gm_*` function names are unchanged; the `gingerminds_core.twig.extension`
  service is replaced by `gingerminds_core.twig.resource`, `.admin` and `.list`.
- **BC break** Repositories no longer know the forms: `RepositoryInterface::save(object $entity,
  bool $flush = true)` (no `?FormInterface $form`), `beforeSave(object $entity)` and
  `afterSave(object $entity)`. The user password is hashed on flush by the
  `UserPasswordListener` Doctrine entity listener (`preFlush`), no longer by
  `UserRepository::beforeSave()` nor `CreateUserCommand` (which loses its password hasher
  argument). The contributor of the user form (`contributorId`, `contributor*` fields) is applied
  by `UserType::submitContributor()` (`POST_SUBMIT`, valid form only, `fillContributor()` moved
  from `UserRepository` too); `BaseUser::$contributor` cascades `persist`.
  `UserRepository::NEW_CONTRIBUTOR` becomes `UserType::NEW_CONTRIBUTOR`; `UserRepository` takes
  only the registry and the entity class.
- **BC break** Controllers extend Symfony's `AbstractController`: `ControllerTrait` (copies of
  `render()`, `redirectToRoute()`, `denyAccessUnlessGranted()`, `isCsrfTokenValid()`,
  `addFlash()`), `ResourceControllerTrait` and `CrudContext` are removed. `AbstractCrudController`
  has no constructor (a subclass injects its own services) and subscribes to the resource
  registry, Doctrine and the translator (`getResourceRegistry()`, `getDoctrine()`, `trans()`);
  `$this->context` is gone, `addFlash()` loses its `Request` argument. Access is declared with
  `#[IsGranted]` on the actions, the delete token with `#[IsCsrfTokenValid]`: an invalid token is
  an `InvalidCsrfTokenException`, turned on the HTML requests of a resource route into the
  `flash.invalid_csrf` flash and a redirection to the same host referer, or else to the list, by
  `InvalidCsrfTokenListener` (`kernel.exception`, before the firewall which would send to the
  login page; JSON requests keep the firewall's handling). The resource is the `_resource` default of the route
  (set by the CRUD route loader): `getResourceName()` and its loop over the registry are removed,
  `getResource()` is not cached any more. `edit()` / `delete()` receive the entity, resolved by
  `ResourceEntityValueResolver` (`#[ValueResolver(ResourceEntityValueResolver::class)]`, 404
  before the access check), instead of a `string $id`; `findEntity()` is removed: an entity of
  another parent (nested resource) is refused by `entityBelongsToRoute()`, a public method checked
  by `ResourceEntityRouteListener` before the access check (404), by default for each `{xxxId}`
  route parameter naming a to-one association `xxx` of the entity. Forms are
  passed to `render()` as `FormInterface` (422 on an invalid submission by `AbstractController`).
  The bundle controllers are service subscribers (`container.service_subscriber`,
  `setContainer()`); `ProfileController` takes Doctrine and the translator, `AuthController` no
  longer takes `Security`.
- **BC break** Security: one `ResourceVoter` for every registered resource replaces
  `AbstractResourceVoter` and its `UserVoter`, `ContributorVoter`, `RoleVoter`, `PermissionVoter`
  (and the voter each resource had to declare). It reads the resource registry (name, configured
  entity class, permission) and implements `supportsAttribute()` / `supportsType()` (voter cache).
  The permission of an attribute is `ResourceDefinition::permissionFor()`, overridable per
  resource with the new `permissions` key (`resources.*.permissions`,
  `#[AsCrudController(permissions: ...)]`): `role` maps every attribute to `manage roles`.
  The own account rules (view/edit always, never delete) are built in; `canView()`...
  `canDelete()` and `getPublicAttributes()` are gone: an additional voter grants more (open
  `VIEW` to anonymous users), an override of `ResourceVoter::voteOnResource()` restricts.
  `make:gm:voter`, `MakeVoter`, the `Voter.tpl.php` skeleton and
  `ResourceGenerator::generateVoter()` are removed (`make:gm:resource` generates no voter).
  `gingerminds:permissions:sync` creates `ResourceDefinition::permissionNames()`.
- **BC break** Routing: the CRUD routes are the `#[Route]` attributes of
  `AbstractCrudController`'s actions (`''`/`index`, `/new`/`new`, `/{id}/edit`/`edit`,
  `/{id}/delete`/`delete`), relative to the resource. `CrudRouteLoader` (`gingerminds_crud`) no
  longer builds them by hand: it loads the attributes of each resource controller (its own
  `#[Route]` actions included, e.g. `#[Route('/reorder', name: 'reorder')]` becomes
  `{route_prefix}_reorder` under the resource path, with `_resource`) and applies the
  registry's path, route prefix and `_resource` default; it stays because path, route prefix
  and controller remain configurable per resource. A resource `controller` must be a class name.
  `ResourceRegistryPass` (now run before `RoutingControllerPass`) removes the CRUD controllers
  from the `routing.controllers` import, which would load their inherited routes without prefix.
  A subclass overriding a CRUD action restates its `#[Route]` / `#[IsGranted]` /
  `#[IsCsrfTokenValid]` (attributes are not inherited): the loader throws otherwise, an override
  would silently lose its route or its access check. Route names and URLs are unchanged. The dashboard, login, logout, profile,
  autocomplete, health and API login/logout routes are `#[Route]` attributes too, imported by
  `config/routes/*.php` (names and paths unchanged); the bundle controllers are aliased by class.
- **BC break** API writes are native API Platform: no Symfony Form any more. `ResourceProcessor`,
  `RoleProcessor`, the `gingerminds_core.api.processor.{user,contributor,role,permission}` services,
  the `Processor.tpl.php` maker skeleton, `ResourceName::processorClass()` and the
  `api.error.invalid_json` translation are removed; the operations lose `deserialize: false` and
  `processor:`. The payload is deserialized with the `denormalizationContext` groups (now real:
  `user:create`, `user:edit`, `role:edit` with `permissions`...), validated by the entity
  constraints, persisted by `api_platform.doctrine.orm.state.persist_processor` /
  `remove_processor`, decorated by `RepositoryProcessor` to run inside the new
  `RepositoryInterface::saveWith()` / `removeWith()` (the repository hooks and transactions of the
  admin: unique default role, contributor released). `make:gm:api` / `--api` generate the provider
  and an `#[ApiResource]` without processor. New API contract:
  - relations by id (`"roles": [3, "4"]`, `"contributor": 12`) or by IRI (`"/api/roles/3"`), through
    the generic `RelationIdDenormalizer`; an unknown id is a `400`. Reads keep embedding the related
    objects with their `id`;
  - `plainPassword` (write only, required on creation by the `user:create` validation group) instead
    of `password: {first, second}`;
  - the user contributor is the `contributor` relation: an id/IRI links an existing one, a nested
    document creates one on `POST` (merged into the current one on `PATCH`, JSON Merge Patch); a new
    contributor replacing the current one is `POST /api/contributors` with `user`.
    `contributorId` / `__new__` / `contributor*` are admin form fields only;
  - errors are the native `422` + `violations`, the Super-Admin role deletion included (a violation
    on `name`, `Delete` with `validate: true` and the new `role:delete` group,
    `BaseRole::GROUP_DELETE`) instead of a `422` with `detail`; the message moves to the
    `validators` domain (`role.super_admin_delete`).

  `AbstractCrudController::getDeleteError()` returns by default the first violation of the
  `{resource}:delete` validation group (`RoleController::getDeleteError()` is removed);
  `UserType` requires the password through the `user:create` validation group instead of a field
  constraint. `UserRepository::save()` / `RoleRepository::save()` overrides become `saveWith()`
  ones; the contributor release moves to `ContributorRepository::writeUserLinks()`, run by the
  user and the contributor saves. `BaseUser::setContributor()` and `BaseContributor::setUser()`
  keep both sides in sync (the side left is released). `BaseUser::$contributor` is
  `#[Assert\Valid]`.

### Added

- Input groups: `input_prefix` / `input_suffix` options on every field (`InputGroupTypeExtension`),
  texts shown before / after a single line input in a Bootstrap input group (Laravel `prefix` /
  `suffix` of the basic input component).
- Nested resources (`path: 'categories/{categoryId}/products'`): `AbstractCrudController::getRouteParameters()`
  (the current route parameters but `id`), kept by the redirects after save and delete and passed to
  the templates as `route_parameters`. `CrudContext` takes the request stack (optional last argument).
- Form include slots: `<resource>_form_main` / `<resource>_form_sidebar` admin includes
  rendered at the end of the main / right column of the admin forms (`profile_form_*` for the
  profile), e.g. for the fields another bundle adds with a form type extension.
  `@GingermindsCore/crud/_form_layout.html.twig` (to `embed`, `main` / `sidebar` blocks; one
  full-width column without sidebar content nor sidebar include) used by the core forms and
  the `make:gm:*` form template, `gm_form_includes(name, column)`.
- Dates are stored in UTC and displayed in `gingerminds_core.display_timezone` (default
  `Europe/Paris`): `datetime` / `datetime_immutable` Doctrine types written and read in UTC,
  API dates serialized in UTC (`framework.serializer.default_context`), the PHP default
  timezone left to `php.ini`, Twig `date` filter and `DateTimeType` fields
  (`model_timezone: UTC`, `view_timezone`) in the display timezone, `date` filters and date
  facets on the days of the display timezone. `DisplayTimezone` service (`toUtc()`,
  `toDisplay()`, `dayRange()`, `dayRangeToUtc()`).
- `gingerminds_core.admin_includes.head`: templates included at the end of `<head>`, after
  the importmap and the admin stylesheet, on every admin page (login included), e.g. the
  assets of a bundle.
- Admin menu weights and sections: `MenuItem` `weight` (ascending, lowest first) and `id`;
  the sections sharing an id are merged whatever provider declares them (e.g. a bundle adds
  entries to the core `administration` section, `CoreAdminMenuProvider::ADMINISTRATION`).
  Core weights: Dashboard -100, Administration 100 (users 0, contributors 10, roles 20,
  permissions 30).
- `gingerminds_core.admin_includes.sidebar_bottom`: templates included at the bottom of the
  sidebar, right above the current user menu (`sidebar_bottom_includes` block).
- Overridable entities for other bundles: `OverriddenEntityPass::registerOverriddenEntity()`
  excludes a replaced bundle entity from the Doctrine mapping and the API resources
  (replaces `CoreEntityPass` and the `gingerminds_core.excluded_entity_files` parameter).
- `gingerminds_core.admin_includes`: templates included in the admin layout by slot
  (`sidebar`, under the logo), `gm_admin_includes()` Twig function, `sidebar_includes` block.
- `make:gm:*` extensions: `ResourceMakerExtensionInterface` (autoconfigured) adds maker
  options, changes the core skeletons (`interfaces`, `traits`, `eager_loads`, `build_form`
  variables) and generates extra files.

- Initial Symfony 8.1 port of `gingerminds/laravel-core`: resource registry, generic CRUD
  controller and route loader, repository with pagination/sort/search/filters/eager loads,
  filter handlers (date, number, boolean, select, select-entity, select-enum), facets,
  User/Contributor/Role/Permission entities (overridable mapped superclasses), voters and
  permissions, admin form login, API opaque tokens, API Platform provider/processor based on
  forms, context header documentation, API response cache,
  timestamps, Twig/Symfony UX admin theme, `make:gm:*` makers,
  `gingerminds:permissions:sync` and `gingerminds:create:user` commands.
- `AbstractResourceVoter::getPublicAttributes()`: attributes granted without authentication,
  e.g. `[self::VIEW]` for a resource readable without an API token.
- Admin theme override: `assets/styles/gingerminds-core/_theme.scss` in the project sets any
  (now `!default`) admin SCSS variable, e.g. `$primary`.
- API rate limit (`gingerminds_core.api.rate_limit`, 60 requests / minute per user or IP by
  default): 429 with `X-RateLimit-*` / `Retry-After` headers, overridable per API Platform
  operation (`rate_limiter` extra property) or route (`_rate_limiter` default).
- Configurable redirect after an admin save (list or edit form): `gingerminds_core.redirect_after_save`
  (`new: index`, `edit: edit` by default), overridden per resource with `redirect_after_new` /
  `redirect_after_edit` (configuration or `#[AsCrudController]`, `RedirectTarget` enum).
- API rate limit (`gingerminds_core.api.rate_limit`, 60 requests / minute per user or IP by
  default): 429 with `X-RateLimit-*` / `Retry-After` headers, overridable per API Platform
  operation (`rate_limiter` extra property) or route (`_rate_limiter` default).
- Paginated admin lists (`crud/list.html.twig`) emit `<link rel="prev">` / `<link rel="next">`
  in the `<head>`, through the new `head` block of the admin layouts.
- Docs: Turbo.md, what Turbo Drive changes for code written for a full page reload (JS run once,
  422 on invalid forms, hover prefetch of `GET` links, page cache), linked from FromLaravel.md.

### Changed

- **BC break** — the API login/logout go through Symfony Security instead of a hand-made
  controller (same URLs, routes, payloads, responses, status codes and rate limit headers):
  `POST /api/login` is the `gingerminds_core.security.api_login_authenticator` custom
  authenticator (success/failure handlers, native `login_throttling` fed by
  `gingerminds_core.api.login_throttling`), `POST /api/logout` dispatches `LogoutEvent`
  through `Security::logout()` (token revoked by `ApiTokenRevocationListener`).
  `LoginSuccessEvent` / `LoginFailureEvent` / `LogoutEvent` are now the extension points;
  `LoginResponseEnricherInterface` is kept. Projects must add the `api_login` firewall (before
  `api`) to `security.yaml`, see Installation.md; without it `/api/login` throws a
  `LogicException`. `AuthController` constructor changed (only `Security` + translator).
- The API is open by default: the recommended `security.yaml` no longer has the `^/api`
  `IS_AUTHENTICATED` rule, each operation is closed by its `security` expression (voter).
- `make:gm:resource` / `make:gm:entity`: the generated entity only has an `id` (no `name`
  field), with the matching form, admin templates and translations.
- Theme docs: a project's brand color goes in `$sidebar-menu-primary-color` (sidebar menu and
  avatar only); overriding `$primary` recolors the whole admin.
- Login page background is white (`secondary-bg`) instead of light indigo (`tertiary-bg`).
- Faster `AbstractRepository::paginate()` on large tables: the total is a plain `COUNT` without
  eager loads nor sort (`COUNT(DISTINCT)` only when a filter joins a collection, skipped on a
  partial page), the `DISTINCT` id subqueries only run when a collection is joined.
- `Contributor`: `contributors_name_idx` index on `(lastname, firstname)`, the list default sort
  (an overriding project entity restates it and generates a migration).
- `BaseUser` eager loads `contributor` and `roleEntities` (`EagerLoadableInterface`): the user
  list no longer runs one query per user (inverse one-to-one contributor, lazy roles).
- `paginate()`: eager loads going through a collection are loaded after the page, one `WHERE IN`
  query per path, instead of being joined (to-one paths stay fetch-joined).
- `AbstractRepository`: eager loading moved to `Repository\Query\EagerLoader` and collection
  join detection to `QueryBuilderHelper::joinsCollection()`. The undocumented protected methods
  `applyEagerLoads()`, `loadEagerCollections()` and `joinsCollection()` are removed.
- `BaseRole` eager loads `permissions` instead of `RoleRepository::configureListQueryBuilder()`
  fetch-joining them: the role list paginates without `DISTINCT` subqueries.
- Docs: "List performance" (eager loads, indexes, search) in ResourceModel.md; the `make:gm:entity`
  template hints at `getEagerLoads()` and `#[ORM\Index]`.
- Forms no longer load a whole table into a `<select>`: the contributor `user` field
  (`ResourceAutocompleteType`) and the user `contributorId` field (`ContributorSelectorType`)
  are searched remotely through `gingerminds_core_autocomplete` and only load the selected /
  submitted entities. On a large users table the contributor form ran one query per user
  (inverse one-to-one) until it ran out of memory. `ResourceAutocompleteType`
  (`['resource' => 'user']`) is reusable by project forms. `UserType::contributorChoices()`
  is deprecated.

### Deprecated

- `MenuItem` `priority` (highest first): use `weight` (ascending). It is read as `weight: -priority`.

### Fixed

- Replacing the contributor of a user who already has one (user form, admin or API: a new one
  or another existing one) no longer fails with a 500 on the unique `contributors.user_id`: a
  flush inserts the new contributor before it updates the old one. `UserRepository::save()`
  releases the contributors the user leaves (`user_id` NULL, DQL `UPDATE`) before the flush, in
  one transaction. The previous contributor is kept, without user.
- No `sidebar_bottom` separator when its templates render nothing (e.g. a conditional switcher).
- Required fields show a `*` after their label again (`.form-label.required`), as the Laravel
  core components did.
