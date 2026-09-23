<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use Gingerminds\CoreBundle\Repository\ListQuery;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @template T of object
 *
 * @implements ProviderInterface<T>
 */
class ResourceProvider implements ProviderInterface
{
    /**
     * @param RepositoryInterface<T> $repository
     */
    public function __construct(
        protected readonly RepositoryInterface $repository,
        protected readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $context['request'] ?? $this->requestStack->getCurrentRequest();
        $query = $request instanceof Request ? ListQuery::fromRequest($request) : new ListQuery();
        $query = $this->configureListQuery($query, $operation, $uriVariables, $context);

        if ($operation instanceof CollectionOperationInterface) {
            $paginator = $this->repository->paginate($query);

            return new TraversablePaginator(
                $paginator->getIterator(),
                $paginator->getPage(),
                $paginator->getItemsPerPage(),
                $paginator->getTotalItems(),
            );
        }

        $id = $uriVariables['id'] ?? null;

        if (!\is_int($id) && !\is_string($id)) {
            return null;
        }

        return $this->repository->findOneForRead($id, $query->withoutFilters(ListQuery::SEARCH_FILTER));
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    protected function configureListQuery(ListQuery $query, Operation $operation, array $uriVariables, array $context): ListQuery
    {
        return $query;
    }
}
