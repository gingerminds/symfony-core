<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

<?= $use_statements ?>

/**
 * Admin CRUD of <?= $resource->snakePlural ?>: routes `admin_<?= $resource->snake ?>_*`, templates `<?= $resource->templateDirectory() ?>/*`.
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
