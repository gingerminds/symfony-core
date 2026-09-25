<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

<?= $use_statements ?>

/**
 * `view|edit|delete <?= $resource->snakePlural ?>` permissions. Override canView()/canCreate()/canEdit()/canDelete() for specific rules,
 * getPublicAttributes() to open an attribute to anonymous users (e.g. `[self::VIEW]`).
 */
final class <?= $class_name ?> extends AbstractResourceVoter
{
    protected function getResourceName(): string
    {
        return '<?= $resource->snake ?>';
    }

    protected function getSubjectClass(): string
    {
        return <?= $entity_class ?>::class;
    }

    protected function getPermissionName(): string
    {
        return '<?= $resource->snakePlural ?>';
    }
}
