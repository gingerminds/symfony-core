# Changelog

All notable changes to this bundle are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed

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

- No `sidebar_bottom` separator when its templates render nothing (e.g. a conditional switcher).
- Required fields show a `*` after their label again (`.form-label.required`), as the Laravel
  core components did.
