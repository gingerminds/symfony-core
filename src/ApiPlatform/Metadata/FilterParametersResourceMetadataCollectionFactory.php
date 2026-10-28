<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Metadata;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\ApiPlatform\Filter\FilterParameters;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;

/**
 * Operations of a Doctrine entity resource:
 *
 * - the collections get the list parameters of the entity attributes (`#[Sortable]`,
 *   `#[Searchable]`, `#[Filter]`, see FilterParameters), unless the operation declares a parameter
 *   of the same key: one declaration for the API and the admin. Runs before the API
 *   Platform parameter factories, which describe them (OpenAPI, Hydra, validation);
 * - the reads of an entity whose repository is a core one (RepositoryInterface) start from its
 *   list query (the `repositoryMethod` of their Doctrine state options, `createListQueryBuilder`:
 *   context scope of configureListQueryBuilder(), `#[EagerLoad]` associations), unless the
 *   operation names its own repository method.
 */
final readonly class FilterParametersResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public const string REPOSITORY_METHOD = 'createListQueryBuilder';

    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $decorated,
        private FilterParameters $filterParameters,
        private ManagerRegistry $registry,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        foreach ($collection as $i => $resource) {
            $operations = $resource->getOperations();

            if (!$operations instanceof Operations) {
                continue;
            }

            foreach ($operations as $name => $operation) {
                $operations->add($name, $this->configure($operation));
            }

            $collection[$i] = $resource->withOperations($operations);
        }

        return $collection;
    }

    private function configure(Operation $operation): Operation
    {
        $stateOptions = $operation->getStateOptions();
        $entityClass = $stateOptions instanceof Options ? $stateOptions->getEntityClass() : null;
        $entityClass ??= $operation->getClass();

        if (null === $entityClass || !class_exists($entityClass)) {
            return $operation;
        }

        $manager = $this->registry->getManagerForClass($entityClass);

        if (!$manager instanceof EntityManagerInterface) {
            return $operation;
        }

        if ($operation instanceof CollectionOperationInterface) {
            $parameters = $operation->getParameters() ?? new Parameters();

            foreach ($this->filterParameters->create($entityClass) as $key => $parameter) {
                if (!$parameters->has($key)) {
                    $parameters->add($key, $parameter);
                }
            }

            $operation = $operation->withParameters($parameters);
        }

        $repositoryClass = $manager->getClassMetadata($entityClass)->customRepositoryClassName;

        // The Doctrine resource factory gives every operation Options (links handler).
        $stateOptions ??= new Options();

        if (
            $stateOptions instanceof Options
            && null === $stateOptions->getRepositoryMethod()
            && null !== $repositoryClass
            && is_a($repositoryClass, RepositoryInterface::class, true)
        ) {
            return $operation->withStateOptions($stateOptions->withRepositoryMethod(self::REPOSITORY_METHOD));
        }

        return $operation;
    }
}
