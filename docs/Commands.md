# Commands reference

## Generators (`make:gm:*`)

Available when `symfony/maker-bundle` is installed (dev). Same resource name convention as the
Laravel core: **`Namespace/Name`**, e.g. `Catalog/Product` or `Media/MediaCategory` — the
namespace becomes the sub-folder/sub-namespace, the name the class name.

| Command | Generates | Laravel core equivalent |
|---|---|---|
| `make:gm:resource` | everything below at once | `make:resource` |
| `make:gm:entity` | `App\Entity\<Ns>\<Name>` (+ `#[ApiResource]` with `--api`) | `make:model` |
| `make:gm:repository` | `App\Repository\<Ns>\<Name>Repository` extending `AbstractRepository` | `make:repository` |
| `make:gm:form` | `App\Form\<Ns>\<Name>Type` | `make:form-request` |
| `make:gm:crud-controller` | `App\Controller\<Ns>\<Name>Controller` (`#[AsCrudController]`) + templates `admin/<name>/*` + translations | `make:controller-full` |
| `make:gm:voter` | `App\Security\Voter\<Ns>\<Name>Voter` extending `AbstractResourceVoter` | `make:policy` |
| `make:gm:api` | `App\State\<Ns>\<Name>Provider` + `<Name>Processor` | `make:api-provider` + `make:state-processor` |

Existing files are never overwritten: they are skipped and reported.

### `make:gm:resource`

```bash
bin/console make:gm:resource Catalog/Product [--api] [--no-controller]
```

| Option | Effect |
|---|---|
| `--api` | Also generates the API provider/processor and the `#[ApiResource]` (5 operations secured by the voter, `deserialize: false`, serialization groups). |
| `--no-controller` | No admin controller, templates nor translations (API-only resource). |

Generated tree for `Catalog/Product --api`:

```
src/Entity/Catalog/Product.php
src/Repository/Catalog/ProductRepository.php
src/Form/Catalog/ProductType.php
src/Security/Voter/Catalog/ProductVoter.php
src/Controller/Catalog/ProductController.php
src/State/Catalog/ProductProvider.php
src/State/Catalog/ProductProcessor.php
templates/admin/product/{index,new,edit,_form}.html.twig
translations/admin.{fr,en}.yaml        (merged, existing keys kept)
```

Then:

```bash
bin/console make:entity 'Catalog\Product'     # add your fields
bin/console doctrine:migrations:diff && bin/console doctrine:migrations:migrate
bin/console gingerminds:permissions:sync       # view|edit|delete products
```

The resource is available at `/admin/products` (routes `admin_product_index|new|edit|delete`)
and, with `--api`, at `/api/products`. No configuration is needed: `#[AsCrudController]`
registers it (see [Configuration](Configuration.md#adding-a-new-resource)).

### `make:gm:voter`

Generates the voter (`view|edit|delete {plural}` permissions). Voters are autoconfigured:
nothing to register (unlike Laravel policies and `AuthServiceProvider`). Remember to run
`gingerminds:permissions:sync`.

### `make:gm:api`

Generates the provider/processor of an existing entity, and prints the `#[ApiResource]`
snippet to paste when the entity does not declare one yet. See [API](API.md).

### Extending the makers

Another bundle adds options to the `make:gm:*` makers by implementing
`Gingerminds\CoreBundle\Maker\Extension\ResourceMakerExtensionInterface` (autoconfigured,
tag `gingerminds_core.maker_extension`; register it only when `symfony/maker-bundle` is
installed). E.g. `--translated` of `gingerminds/symfony-multisite`.

| Method | Role |
|---|---|
| `configureCommand($commandName, $command)` | adds the options to a maker (`make:gm:resource`, `make:gm:entity`...) |
| `isEnabled($input)` | whether the extension takes part in the run (its option is set) |
| `configureTemplate(SkeletonTemplate $template, $resource)` | before a core skeleton is rendered: `addUse()`, `set()` / `append()` variables, or replace `$template->path` |
| `generate(ResourceMakerContext $context)` | extra files (`$context->resourceGenerator->generateClassFromSkeleton()`, `addTranslations()`), returns next steps |

Extensible skeleton variables: `Entity.tpl.php` `interfaces`, `traits` (short names, import
them with `addUse()`) and `eager_loads` (PHP expressions; non-empty: the entity implements
`EagerLoadableInterface`); `FormType.tpl.php` `build_form` (lines of `buildForm()`). Every
skeleton receives `skeleton_directory` (the core skeleton directory).

## Runtime commands

| Command | Purpose |
|---|---|
| `gingerminds:permissions:sync` | Creates the missing permissions (core ones, `view\|edit\|delete` of every resource, `gingerminds_core.permissions`) and the Super-Admin/Admin roles. Idempotent, never deletes — run it on every deploy. Replaces the Laravel `PermissionSeeder`. |
| `gingerminds:create:user` | Interactive (or `--email --role --lastname --firstname --password`): creates a user, its contributor and assigns a role, in one transaction. |

## See also

- [Resource model](ResourceModel.md)
- [Configuration](Configuration.md)
