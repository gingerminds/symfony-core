# Installation

## 1. Require the bundle

```bash
composer require gingerminds/core-bundle
```

Register it (Flex does it for you) in `config/bundles.php`, together with its dependencies:

```php
return [
    // ...
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
    ApiPlatform\Symfony\Bundle\ApiPlatformBundle::class => ['all' => true],
    Symfony\UX\StimulusBundle\StimulusBundle::class => ['all' => true],
    Symfony\UX\Turbo\TurboBundle::class => ['all' => true],
    Symfony\UX\Autocomplete\AutocompleteBundle::class => ['all' => true],
    Symfonycasts\SassBundle\SymfonycastsSassBundle::class => ['all' => true],
    Gingerminds\CoreBundle\GingermindsCoreBundle::class => ['all' => true],
];
```

## 2. Routes

```yaml
# config/routes/gingerminds_core.yaml
gingerminds_core:
    resource: '@GingermindsCoreBundle/config/routes.php'
```

This imports the admin routes (under `admin_prefix`), the generated CRUD routes of every
resource, the API authentication routes (`/api/login`, `/api/logout`) and the health check
(`/health`). API Platform routes are imported as usual (`config/routes/api_platform.yaml`).

## 3. Security

The bundle cannot own your firewalls: copy this into `config/packages/security.yaml` and
adapt it. The prefixes must match `gingerminds_core.admin_prefix` / `gingerminds_core.api.prefix`.

```yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: auto

    providers:
        gingerminds_users:
            id: gingerminds_core.security.user_provider

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js|assets)/
            security: false
        api:
            pattern: ^/api
            stateless: true
            provider: gingerminds_users
            access_token:
                token_handler: gingerminds_core.security.api_token_handler
                failure_handler: gingerminds_core.security.api_failure_handler
        admin:
            lazy: true
            provider: gingerminds_users
            form_login:
                login_path: gingerminds_core_login
                check_path: gingerminds_core_login
                default_target_path: gingerminds_core_dashboard
                enable_csrf: true
            login_throttling:
                max_attempts: 5
                interval: '5 minutes'
            remember_me:
                secret: '%kernel.secret%'
                lifetime: 604800
            logout:
                path: gingerminds_core_logout
                target: gingerminds_core_login

    access_control:
        - { path: ^/admin/login$, roles: PUBLIC_ACCESS }
        - { path: ^/admin, roles: IS_AUTHENTICATED }
        - { path: ^/api/login$, roles: PUBLIC_ACCESS }
        - { path: ^/api/docs, roles: PUBLIC_ACCESS }
        - { path: ^/api, roles: IS_AUTHENTICATED }
```

`login_throttling` requires `symfony/rate-limiter` (already a dependency of the bundle).
Keep the default `affirmative` access decision strategy: the Super-Admin bypass relies on it.

## 4. API Platform

```yaml
# config/packages/api_platform.yaml
api_platform:
    formats:
        jsonld: ['application/ld+json']
        json: ['application/json']
    patch_formats:
        json: ['application/merge-patch+json', 'application/json']
    defaults:
        stateless: true
        pagination_client_items_per_page: true
```

The bundle adds its own entities to `api_platform.mapping.paths` automatically.

## 5. Database

The bundle ships no migration (Symfony convention): generate them from the mapping.

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
bin/console gingerminds:permissions:sync   # permissions + Super-Admin/Admin roles, idempotent
bin/console gingerminds:create:user        # first admin
```

Run `gingerminds:permissions:sync` on every deploy: it only creates what is missing.

## 6. Admin assets (AssetMapper + Symfony UX)

No Node build. Install the JavaScript packages used by the admin in your importmap:

```bash
bin/console importmap:require bootstrap @popperjs/core bootstrap-icons/font/bootstrap-icons.min.css sortablejs tom-select tom-select/dist/css/tom-select.bootstrap5.css
```

and add the admin entrypoint to `importmap.php`:

```php
'gingerminds-core-admin' => [
    'path' => 'gingerminds-core/admin.js',
    'entrypoint' => true,
],
```

`@hotwired/stimulus`, `@symfony/stimulus-bundle` and `@symfony/ux-autocomplete` come from
their Flex recipes. In `assets/controllers.json`, keep `@symfony/ux-autocomplete` enabled and
switch its autoimport to the Bootstrap 5 theme (the admin already imports it):

```json
"autoimport": {
    "tom-select/dist/css/tom-select.default.css": false,
    "tom-select/dist/css/tom-select.bootstrap5.css": true
}
```

The admin pages only load the `gingerminds-core-admin` entrypoint (it starts its own Stimulus
application): do not also load your front `app` entrypoint on them.

The admin stylesheet is Bootstrap 5 SCSS compiled by `symfonycasts/sass-bundle`: the bundle
registers its own entry and the `vendor/twbs` load path (Bootstrap sources come from the
`twbs/bootstrap` Composer package). If your project has no `assets/styles/app.scss`, declare
your own `symfonycasts_sass.root_sass` explicitly.

```bash
bin/console sass:build          # or sass:build --watch in development
bin/console asset-map:compile   # production
```

## Next steps

- [Configuration](Configuration.md)
- [Resource model](ResourceModel.md), then `bin/console make:gm:resource Catalog/Product --api` ([Commands](Commands.md))
