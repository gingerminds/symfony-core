<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Form\Autocomplete;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Gingerminds\CoreBundle\ApiPlatform\Filter\FilterParameters;
use Gingerminds\CoreBundle\Model\ResourceInterface;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\UX\Autocomplete\EntityAutocompleterInterface;

/**
 * UX Autocomplete source of a resource, aliased by its name (`gingerminds_core_autocomplete`,
 * `/_autocomplete/{alias}` under the admin prefix): one per registered resource, declared by
 * ResourceRegistryPass. The search is the `search` filter of the lists, on the list query
 * without its eager loads; access is the VIEW permission of the resource.
 *
 * @implements EntityAutocompleterInterface<object>
 */
final readonly class ResourceAutocompleter implements EntityAutocompleterInterface
{
    public const int MAX_RESULTS = 20;

    public function __construct(
        private string $resource,
        private ResourceRegistry $resources,
        private FilterParameters $filterParameters,
    ) {
    }

    public function getEntityClass(): string
    {
        return $this->resources->getEntityClass($this->resource);
    }

    public function createFilteredQueryBuilder(EntityRepository $repository, string $query): QueryBuilder
    {
        if (!$repository instanceof RepositoryInterface) {
            throw new NotFoundHttpException(\sprintf('The resource "%s" is not searchable.', $this->resource));
        }

        $entityClass = $this->getEntityClass();
        $qb = $repository->createScopedQueryBuilder();
        $repository->applyParameters($qb, $this->filterParameters->fromQuery($entityClass, [FilterParameters::SEARCH => trim($query)]));

        // Stable pagination: always end with the identifier.
        $identifier = $qb->getEntityManager()->getClassMetadata($entityClass)->getSingleIdentifierFieldName();

        return $qb->addOrderBy($qb->getRootAliases()[0] . '.' . $identifier, \SortDirection::Ascending)
            ->setMaxResults(self::MAX_RESULTS);
    }

    public function getLabel(object $entity): string
    {
        return $entity instanceof \Stringable ? (string) $entity : '#' . $this->getValue($entity);
    }

    public function getValue(object $entity): string
    {
        return $entity instanceof ResourceInterface ? (string) $entity->getId() : '';
    }

    public function getAttributes(object $entity): array
    {
        return [];
    }

    public function isGranted(Security $security): bool
    {
        return $security->isGranted(ResourceVoter::VIEW, $this->resource);
    }

    public function getGroupBy(): mixed
    {
        return null;
    }

    public function getTranslationDomain(): false
    {
        return false;
    }
}
