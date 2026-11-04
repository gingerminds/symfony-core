<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\State;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\ApiPlatform\Metadata\FilterParametersResourceMetadataCollectionFactory;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;

/**
 * Decorates the API Platform Doctrine collection provider: a collection read from
 * createCollectionQueryBuilder() (to-one eager loads only, see
 * FilterParametersResourceMetadataCollectionFactory) gets the `#[EagerLoad]` paths going
 * through a collection loaded for its page, one query per path instead of one per row. The
 * page and its count stay plain queries, without DISTINCT over the joined rows.
 *
 * @implements ProviderInterface<object>
 */
final readonly class EagerCollectionProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(
        private ProviderInterface $decorated,
        private ManagerRegistry $registry,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $result = $this->decorated->provide($operation, $uriVariables, $context);
        $options = $operation->getStateOptions();

        if (
            !is_iterable($result)
            || !$options instanceof Options
            || FilterParametersResourceMetadataCollectionFactory::COLLECTION_REPOSITORY_METHOD !== $options->getRepositoryMethod()
        ) {
            return $result;
        }

        $entityClass = $options->getEntityClass() ?? $operation->getClass();
        $repository = null !== $entityClass && class_exists($entityClass) ? $this->registry->getRepository($entityClass) : null;

        if ($repository instanceof RepositoryInterface) {
            // The API Platform paginator keeps its iterator: the page is not fetched twice.
            $repository->loadEagerCollections(array_values(\is_array($result) ? $result : iterator_to_array($result, false)));
        }

        return $result;
    }
}
