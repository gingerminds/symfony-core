# Changelog

All notable changes to this bundle are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

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

### Changed

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
