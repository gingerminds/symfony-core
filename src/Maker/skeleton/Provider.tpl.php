<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

<?= $use_statements ?>

/**
 * @extends ResourceProvider<<?= $entity_class ?>>
 */
final class <?= $class_name ?> extends ResourceProvider
{
    public function __construct(<?= $repository_class ?> $repository, RequestStack $requestStack)
    {
        parent::__construct($repository, $requestStack);
    }

    // Turn URI variables into filters, e.g. for `/api/categories/{category}/items`:
    //
    // protected function configureListQuery(
    //     \Gingerminds\CoreBundle\Repository\ListQuery $query,
    //     \ApiPlatform\Metadata\Operation $operation,
    //     array $uriVariables,
    //     array $context,
    // ): \Gingerminds\CoreBundle\Repository\ListQuery {
    //     return isset($uriVariables['category']) ? $query->withFilter('category', $uriVariables['category']) : $query;
    // }
}
