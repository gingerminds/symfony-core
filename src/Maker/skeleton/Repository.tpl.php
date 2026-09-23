<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

<?= $use_statements ?>

/**
 * @extends AbstractRepository<<?= $entity_class ?>>
 */
class <?= $class_name ?> extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, <?= $entity_class ?>::class);
    }

    // Business logic run before each save (admin form and API processor):
    //
    // protected function beforeSave(object $entity, ?\Symfony\Component\Form\FormInterface $form): void
    // {
    //     $entity->setSlug(...);
    // }
}
