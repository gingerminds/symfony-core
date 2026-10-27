<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

<?= $use_statements ?>

/**
 * Admin CRUD of <?= $resource->snakePlural ?>: routes `admin_<?= $resource->snake ?>_*`, templates `<?= $resource->templateDirectory() ?>/*`.
 *
 * The CRUD routes are the #[Route] of AbstractCrudController, loaded by the core under the
 * resource path. An action of its own is relative to it too:
 * `#[Route('/export', name: 'export')]` serves `/{admin_prefix}/<?= str_replace('_', '-', $resource->snakePlural) ?>/export`, route `admin_<?= $resource->snake ?>_export`.
 */
#[AsCrudController(
    resource: '<?= $resource->snake ?>',
    entity: <?= $entity_class ?>::class,
    form: <?= $form_class ?>::class,
    translationDomain: 'admin',
)]
final class <?= $class_name ?> extends AbstractCrudController
{
}
