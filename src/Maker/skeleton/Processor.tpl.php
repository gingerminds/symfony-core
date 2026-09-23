<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

<?= $use_statements ?>

/**
 * Submits the API payload to <?= $form_class ?> (same mapping and validation as the admin).
 *
 * @extends ResourceProcessor<<?= $entity_class ?>>
 */
final class <?= $class_name ?> extends ResourceProcessor
{
    public function __construct(<?= $repository_class ?> $repository, FormFactoryInterface $formFactory, RequestStack $requestStack)
    {
        parent::__construct($repository, $formFactory, $requestStack, <?= $form_class ?>::class);
    }
}
