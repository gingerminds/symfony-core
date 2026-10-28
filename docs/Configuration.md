# Configuration & resource extensibility

Full reference: `bin/console config:dump-reference gingerminds_core`.

```yaml
# config/packages/gingerminds_core.yaml
gingerminds_core:
    admin_prefix: admin              # URL prefix of the admin (routes + your access_control)
    admin_title: 'admin.title'       # translation key (plain text also works)
    admin_title_translation_domain: GingermindsCore
    health_check_path: health        # public GET /health -> {"status": "ok"}
    display_timezone: Europe/Paris   # dates typed and displayed in this timezone, stored in UTC

    profile:
        form: Gingerminds\CoreBundle\Form\User\ProfileType   # form type of the profile page (/admin/profile)

    security:
        authorized_domains: []       # allowed "Origin" hosts of the admin login, [] = no check

    api:
        prefix: api                  # prefix of /api/login and /api/logout
        token_ttl: null              # API token lifetime in seconds, null = no expiration
        login_throttling:            # API login (firewall login_throttling.limiter: gingerminds_core.security.api_login_rate_limiter)
            max_attempts: 5
            interval: '5 minutes'
        rate_limit:                  # every request under the API prefix, per user (IP when anonymous)
            enabled: true
            limit: 60
            interval: '1 minute'     # sliding window

    cache:
        enabled: true                # global switch of the API response cache
        default_ttl: 3600
        pool: gingerminds_core.resource_cache

    permissions: []                  # extra permissions created by gingerminds:permissions:sync

    redirect_after_save:             # after a save in the admin: list (index) or form (edit)
        new: index
        edit: edit

    admin_includes:                  # templates included in the admin layout, by slot
        sidebar: []                  # under the logo (see templating/layouts.md)
        sidebar_bottom: []           # above the current user menu

    resources: {}                    # see below
```

## Route prefix

`admin_prefix` prefixes every admin route of the bundle (login, dashboard, profile, CRUD
routes, autocomplete): `#[Route]` attributes on the bundle controllers, imported under the prefix
by `@GingermindsCoreBundle/config/routes.php` (the autocomplete is UX Autocomplete's controller); the CRUD routes by the `gingerminds_crud` loader
(`resources.*.path` / `route_prefix` for each resource). Protection itself is your firewall's `access_control` (see
[Authentication](Authentication.md)): keep both in sync.

## Redirect after save

Where the admin goes after creating (`new`) or updating (`edit`) a resource: its list
(`index`) or its edit form (`edit`). `redirect_after_save` sets it for every resource, each
resource overrides it with `redirect_after_new` / `redirect_after_edit`:

```yaml
gingerminds_core:
    redirect_after_save:
        new: edit                    # stay on the form after a creation
    resources:
        user:
            redirect_after_edit: index
```

```php
#[AsCrudController(resource: 'page', entity: Page::class, redirectAfterEdit: RedirectTarget::Index)]
```

For anything else (another route, a query string...), override
`AbstractCrudController::redirectAfterSave()`.

## Dates and timezone

Every date of the system is UTC; only what a user types or reads uses `display_timezone`
(default `Europe/Paris`, `'%env(APP_DISPLAY_TIMEZONE)%'` works):

- storage: the `datetime` and `datetime_immutable` Doctrine types are replaced by UTC ones
  (written in UTC whatever the timezone of the instance, hydrated as UTC);
- API output: the serializer writes every date in UTC (prepended
  `framework.serializer.default_context.datetime_timezone`);
- the bundle does not change the PHP default timezone (`new \DateTimeImmutable()`, logs): set
  `date.timezone` in your `php.ini` (`UTC` recommended), storage and API output do not depend on it;
- display: the Twig `date` filter (and `format_date*`) uses the display timezone
  (prepended `twig.date.timezone`);
- forms: `DateTimeType` defaults to `model_timezone: UTC` and `view_timezone: <display>`.
  `DateType` (a day, no time) is not converted;
- list filters and facets: the `date` filter bounds are whole days of the display timezone
  (a `date` column is compared as is); facet bounds and years are display days.

`Gingerminds\CoreBundle\Service\DisplayTimezone` (autowirable) does the same in your code:
`toUtc('2026-07-01 10:30')`, `toDisplay($entity->getPublishedAt())`,
`dayRangeToUtc('2026-07-01', '2026-07-31')`.

> Existing data written in another timezone must be migrated to UTC.

## Health check route

`GET /{health_check_path}` always answers `200 {"status": "ok"}`, outside the admin prefix
and the firewalls' access control. Point CI/monitoring at it rather than `/`.

## Resources: the `resources` array

Every admin resource is described by a `ResourceDefinition` held in the
`Gingerminds\CoreBundle\Resource\ResourceRegistry` service (the Laravel core
`ResourceResolver`):

| Key                  | Default (core resources)                 | Default (project resources)   |
|----------------------|------------------------------------------|-------------------------------|
| `entity`             | bundle entity (`Entity\User\User`...)    | **required**                  |
| `controller`         | bundle controller                        | the `#[AsCrudController]` class |
| `form`               | bundle form type                         | –                             |
| `path`               | `users`, `roles`...                      | kebab-case plural (`product-categories`) |
| `permission`         | `users`, `roles`...                      | snake_case plural (`product_categories`) |
| `route_prefix`       | `gingerminds_core_<name>`                | `admin_<name>`                |
| `translation_prefix` | `<name>`                                 | `<name>`                      |
| `translation_domain` | `GingermindsCore`                        | `messages`                    |
| `template_prefix`    | `@GingermindsCore/pages/<name>`          | `admin/<name>`                |
| `redirect_after_new` | `redirect_after_save.new` (`index`)      | `redirect_after_save.new` (`index`) |
| `redirect_after_edit`| `redirect_after_save.edit` (`edit`)      | `redirect_after_save.edit` (`edit`) |
| `permissions`        | `manage roles` for every attribute of `role` | `{}`: `view\|edit\|edit\|delete <permission>` |

Sources are merged in this order (last wins): core resources, `#[AsCrudController]`
controllers, `gingerminds_core.resources` configuration. Only the keys you set are overridden.

```php
$registry->get('user')->entity;          // configured user entity
$registry->get('product')->route('edit'); // admin_product_edit
$registry->findByEntity($product);       // ResourceDefinition|null
```

## Overriding a built-in resource

> Never edit the bundle. Extend in the project, then point the configuration at your class.

### Entity

Extend the **base class** (`BaseUser`, a Doctrine mapped superclass), not the bundle `User` entity, and restate
the class-level attributes (PHP attributes are not inherited):

```php
namespace App\Entity\User;

use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Entity\User\BaseUser;
use Gingerminds\CoreBundle\Repository\User\UserRepository;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
// + #[ApiResource(...)] if the user must stay exposed, see API.md
class User extends BaseUser
{
    #[ORM\Column(nullable: true)]
    private ?string $phone = null;
}
```

```yaml
gingerminds_core:
    resources:
        user:
            entity: App\Entity\User\User
```

Class-level `#[ORM\Index]` are not inherited either: an entity overriding `Contributor` restates
`#[ORM\Index(name: 'contributors_name_idx', fields: ['lastname', 'firstname'])]` (default sort
of the contributor list), then generates its migration.

The bundle then:

- excludes its own `Entity/User/User.php` from the Doctrine mapping and the API resources
  (no duplicate table, no duplicate API resource —
  this replaces the Laravel `ClassHierarchyResourceNameCollectionFactory` hack);
- resolves `UserInterface` to your class everywhere (`resolve_target_entities`), so every
  relation (`Contributor::$user`, `ApiToken::$user`...) targets it;
- uses it in forms, commands, the resource voter and repositories.

### Repository

The `repositoryClass` of the project entity is used everywhere, the authentication and the
forms included: `gingerminds_core.repository.<name>` (`user`, `contributor`, `role`,
`permission`) is the repository Doctrine gives for the configured entity, autowired by
`UserRepositoryInterface` / `ContributorRepositoryInterface`. Extend the bundle repository
(or implement its interface) and register it as usual (`ServiceEntityRepository`, autoconfigured):

```php
#[ORM\Entity(repositoryClass: App\Repository\UserRepository::class)]
class User extends BaseUser {}

final class UserRepository extends \Gingerminds\CoreBundle\Repository\User\UserRepository
{
    public function loadUserByIdentifier(string $identifier): ?UserInterface { /* sign in by phone too */ }
}
```

The API tokens (entity not overridable) go through `ApiTokenRepositoryInterface`
(`gingerminds_core.repository.api_token`): decorate it to change their storage.

Property mapping, validation constraints and serialization groups **are** inherited from the
base class; only `#[ORM\Entity]`, `#[ORM\Table]` and `#[ApiResource]` must be restated.
The same works for `contributor` (`BaseContributor`), `role` (`BaseRole`, keep the
`roles_default_unique` unique constraint on `is_external, is_default`) and `permission`
(`BasePermission`).

### Overridable entities of another bundle

A bundle shipping its own overridable entities and admin resources (e.g.
`gingerminds/symfony-multisite`) extends `Gingerminds\CoreBundle\Bundle\AbstractResourceBundle`,
as the core does:

```php
final class GingermindsMultisiteBundle extends AbstractResourceBundle
{
    public const array RESOURCES = [
        'site' => [
            'entity' => Site::class,              // default class, `resources.site.entity` overrides it
            'interface' => SiteInterface::class,  // resolve_target_entities
            'controller' => SiteController::class,
            'form' => SiteType::class,
            'path' => 'sites',
            'permission' => 'sites',
        ],
    ];

    protected function resources(array $config): array
    {
        return self::RESOURCES;  // `$config` for conditional resources; `admin: false` for an entity only
    }

    protected function entityDirectories(): array
    {
        return ['Site', 'Language'];  // src/Entity/<dir>, Doctrine attribute mappings
    }
}
```

`loadExtension()` and `prependExtension()` (call the parent when overriding them) then:

- set `<alias>.resource.<name>.entity` (the configured class) for the services;
- register the replaced bundle entities (`OverriddenEntityPass`): the core excludes them from
  the Doctrine attribute mapping and from the API Platform resources;
- register the admin resources (`<alias>_<name>` routes, the bundle translation domain,
  `@<Bundle>/pages/<name>` templates). A project's `#[AsCrudController(resource: 'site')]` and
  `gingerminds_core.resources.site` override them, in that order;
- prepend the `doctrine.orm.mappings` and `resolve_target_entities`.

`resolveConfig()` processes the bundle configuration while prepending (`prependConfigKeys()`:
`resources` only by default, the other keys may hold env placeholders);
`AbstractResourceBundle::extensionConfigValue($builder, 'gingerminds_multisite', 'translation.log_channel')`
reads a raw value of any extension (the last non-empty one).

The bundle repositories extending `AbstractRepository` get the API Platform `ParameterExtension`
from the core (`RepositoryParameterExtensionPass`): declare them without `setParameterExtension()`.

### Controller, form, templates, translations

```yaml
gingerminds_core:
    resources:
        user:
            controller: App\Controller\User\UserController    # a class extending the bundle UserController
            form: App\Form\UserType                           # extends the bundle UserType
            template_prefix: admin/user
```

The profile page of the logged in user (`/admin/profile`) is not a resource: its form type is
the `profile.form` option, a `FormTypeInterface` (usually a class extending the bundle
`ProfileType`). The bundle template renders the `ProfileType` fields, the extra ones under them
(`form_rest()`); override `pages/profile/edit.html.twig` to place them.

```yaml
gingerminds_core:
    profile:
        form: App\Form\ProfileType                            # extends the bundle ProfileType
```

Templates can also be overridden one by one with Symfony's standard mechanism:
`templates/bundles/GingermindsCoreBundle/pages/user/index.html.twig`.

### Services

Every bundle service has a `gingerminds_core.*` id: decorate or replace it as usual
(`bin/console debug:container gingerminds_core`). For instance the list parameters factory is
`gingerminds_core.api.filter_parameters`.

## Adding a new resource

`bin/console make:gm:resource Catalog/Product --api` (see [Commands](Commands.md)) generates
an `#[AsCrudController]` controller: the resource is registered, its CRUD routes generated,
its permissions picked up by `gingerminds:permissions:sync`. No configuration needed.

A resource without admin controller (e.g. only used by `relation` filters and the
autocomplete endpoint) is declared in configuration:

```yaml
gingerminds_core:
    resources:
        category:
            entity: App\Entity\Catalog\Category
```

## See also

- [Resource model](ResourceModel.md)
- [Authentication](Authentication.md)
- [Commands](Commands.md)
