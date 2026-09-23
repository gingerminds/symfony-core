# Gingerminds Core Bundle

Core bundle for Gingerminds Symfony admin panels — the Symfony 8 counterpart of
`gingerminds/laravel-core`:

- generic, extensible CRUD (repository + form + voter + controller), with generators;
- admin authentication (form login, throttling, remember me) and API authentication
  (revocable opaque tokens);
- roles and permissions administrable from the admin, enforced by voters;
- API Platform 4 on the same repositories and forms as the admin;
- filters, search, sorting, facets and API response cache;
- Twig + AssetMapper + Symfony UX admin theme (Bootstrap 5), no Node build.

Requires PHP 8.4, Symfony 8.1, Doctrine ORM 3, API Platform 4.4.

## Installation

```bash
composer require gingerminds/core-bundle
```

Then follow [Installation](docs/Installation.md) (routes, `security.yaml`, API Platform,
database, assets).

## Documentation

**Getting started**

- [Installation](docs/Installation.md)
- [Configuration](docs/Configuration.md) — options, resource registry, overriding a built-in resource.
- [Resource model](docs/ResourceModel.md) — entity, repository, form, voter, controller.
- [Commands](docs/Commands.md) — `make:gm:*` generators and `gingerminds:*` commands.
- [Coming from laravel-core](docs/FromLaravel.md) — concept mapping and behaviour changes.

**Admin panel**

- [Authentication](docs/Authentication.md) — login flow, admin protection, API tokens.
- [Users, roles & permissions](docs/User.md)
- [Layouts](docs/templating/layouts.md) — list, tree, form, tabs, show.
- [Forms](docs/templating/forms.md) — form theme, sizes, toggles, autocomplete.
- [Filters](docs/partials/filters.md) and [Facets](docs/partials/facets.md)
- [Sorting](docs/Sorting.md) — column sorting and drag & drop reordering.

**API**

- [API](docs/API.md) — providers, processors, overriding resources, context headers.
- [Cache](docs/Cache.md) — API response cache and invalidation.

## Development

```bash
composer install
make assets        # importmap + sass for the test application
vendor/bin/phpunit
make qa            # phpstan, phpcs, phpunit
make serve         # test application on http://127.0.0.1:8000/admin
```

The test application (`tests/Application`) is a minimal kernel using the bundle, with a
`Product`/`Category` resource exercising every list feature.
