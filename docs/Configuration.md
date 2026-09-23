# Configuration & resource extensibility

Full reference: `bin/console config:dump-reference gingerminds_core`.

```yaml
# config/packages/gingerminds_core.yaml
gingerminds_core:
    admin_prefix: admin              # URL prefix of the admin (routes + your access_control)
    admin_title: 'admin.title'       # translation key (plain text also works)
    admin_title_translation_domain: GingermindsCore
    health_check_path: health        # public GET /health -> {"status": "ok"}

    security:
        authorized_domains: []       # allowed "Origin" hosts of the admin login, [] = no check

    api:
        prefix: api                  # prefix of /api/login and /api/logout
        token_ttl: null              # API token lifetime in seconds, null = no expiration
        login_throttling:
            max_attempts: 5
            interval: '5 minutes'

    cache:
        enabled: true                # global switch of the API response cache
        default_ttl: 3600
        pool: gingerminds_core.resource_cache

    permissions: []                  # extra permissions created by gingerminds:permissions:sync

    resources: {}                    # see below
```

## Route prefix

`admin_prefix` prefixes every admin route of the bundle (login, dashboard, profile, CRUD
routes, autocomplete). Protection itself is your firewall's `access_control` (see
[Authentication](Authentication.md)): keep both in sync.

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

The bundle then:

- excludes its own `Entity/User/User.php` from the Doctrine mapping and the API resources
  (no duplicate table, no duplicate API resource —
  this replaces the Laravel `ClassHierarchyResourceNameCollectionFactory` hack);
- resolves `UserInterface` to your class everywhere (`resolve_target_entities`), so every
  relation (`Contributor::$user`, `ApiToken::$user`...) targets it;
- uses it in forms, commands, voters and repositories.

Property mapping, validation constraints and serialization groups **are** inherited from the
base class; only `#[ORM\Entity]`, `#[ORM\Table]` and `#[ApiResource]` must be restated.
The same works for `contributor` (`BaseContributor`), `role` (`BaseRole`, keep the
`roles_default_unique` unique constraint on `is_external, is_default`) and `permission`
(`BasePermission`).

### Controller, form, templates, translations

```yaml
gingerminds_core:
    resources:
        user:
            controller: App\Controller\User\UserController    # extends the bundle UserController
            form: App\Form\UserType                           # extends the bundle UserType
            template_prefix: admin/user
```

Templates can also be overridden one by one with Symfony's standard mechanism:
`templates/bundles/GingermindsCoreBundle/pages/user/index.html.twig`.

### Services

Every bundle service has a `gingerminds_core.*` id: decorate or replace it as usual
(`bin/console debug:container gingerminds_core`). For instance the API provider of users is
`gingerminds_core.api.provider.user`.

## Adding a new resource

`bin/console make:gm:resource Catalog/Product --api` (see [Commands](Commands.md)) generates
an `#[AsCrudController]` controller: the resource is registered, its CRUD routes generated,
its permissions picked up by `gingerminds:permissions:sync`. No configuration needed.

A resource without admin controller (e.g. only used by `select-entity` filters and the
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
