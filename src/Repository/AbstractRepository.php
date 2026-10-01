<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Model\FilterableInterface;
use Gingerminds\CoreBundle\Model\SearchableInterface;
use Gingerminds\CoreBundle\Model\SortableInterface;
use Gingerminds\CoreBundle\Pagination\Paginator;
use Gingerminds\CoreBundle\Repository\Filter\FilterHandlerRegistry;
use Gingerminds\CoreBundle\Repository\Query\EagerLoader;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @template T of object
 *
 * @extends ServiceEntityRepository<T>
 *
 * @implements RepositoryInterface<T>
 */
abstract class AbstractRepository extends ServiceEntityRepository implements RepositoryInterface
{
    public const string ALIAS = 'o';

    protected int $itemsPerPage = 10;

    protected int $maxItemsPerPage = 200;

    private ?FilterHandlerRegistry $filterHandlers = null;

    /**
     * @param class-string<T> $entityClass
     */
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, $entityClass);
    }

    #[Required]
    public function setFilterHandlerRegistry(FilterHandlerRegistry $filterHandlers): void
    {
        $this->filterHandlers = $filterHandlers;
    }

    /**
     * @return class-string<T>
     */
    public function getEntityClass(): string
    {
        return $this->getClassName();
    }

    public function paginate(ListQuery $query): Paginator
    {
        $itemsPerPage = min($this->maxItemsPerPage, $query->itemsPerPage ?? $this->itemsPerPage);
        $offset = ($query->page - 1) * $itemsPerPage;
        // Collection eager loads are loaded after the page (EagerLoader::loadCollections()).
        $qb = $this->buildListQueryBuilder($query, [], eagerCollections: false)
            ->setFirstResult($offset)
            ->setMaxResults($itemsPerPage);

        // The id subqueries (DISTINCT) are only needed when a collection is joined:
        // otherwise LIMIT applies to the entities themselves.
        $joinsCollection = new QueryBuilderHelper($qb, self::ALIAS, $this->getEntityManager())->joinsCollection();
        $doctrinePaginator = new DoctrinePaginator($qb, fetchJoinCollection: $joinsCollection);

        /** @var list<T> $items */
        $items = iterator_to_array($doctrinePaginator->getIterator(), false);
        $this->createEagerLoader()->loadCollections($items, self::ALIAS);

        // A partial page gives the total without counting (not for an empty page past the end).
        $total = [] !== $items && \count($items) < $itemsPerPage
            ? $offset + \count($items)
            : $this->countForList($query) ?? \count($doctrinePaginator);

        return new Paginator($items, $total, $query->page, $itemsPerPage);
    }

    public function findForList(ListQuery $query): array
    {
        /** @var list<T> */
        return $this->createListQueryBuilder($query)->getQuery()->getResult();
    }

    public function createListQueryBuilder(ListQuery $query, array $excludedFilters = []): QueryBuilder
    {
        return $this->buildListQueryBuilder($query, $excludedFilters, eagerCollections: true);
    }

    /**
     * @param list<string> $excludedFilters
     */
    private function buildListQueryBuilder(ListQuery $query, array $excludedFilters, bool $eagerCollections): QueryBuilder
    {
        $qb = $this->createQueryBuilder(self::ALIAS);
        $helper = new QueryBuilderHelper($qb, self::ALIAS, $this->getEntityManager());

        $this->createEagerLoader()->apply($helper, $eagerCollections);
        $this->configureListQueryBuilder($qb, $query);

        $filters = array_diff_key($query->filters, array_flip($excludedFilters));

        $this->applyItem($helper, $filters);
        $this->applySearch($helper, $filters);
        $this->applyFilters($helper, $filters);
        $this->applySort($helper, $query);

        return $qb;
    }

    public function findOneForRead(int|string $id, ?ListQuery $query = null): ?object
    {
        $query = ($query ?? new ListQuery())->withFilter(ListQuery::ID_FILTER, $id)->withSort(null);

        // No setMaxResults(): it would truncate fetch-joined collections.
        /** @var list<T> $result */
        $result = $this->createListQueryBuilder($query)->getQuery()->getResult();

        return $result[0] ?? null;
    }

    public function save(object $entity, ?FormInterface $form = null, bool $flush = true): void
    {
        $this->beforeSave($entity, $form);

        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        $this->afterSave($entity, $form);
    }

    public function remove(object $entity, bool $flush = true): void
    {
        $this->beforeRemove($entity);

        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    private function createEagerLoader(): EagerLoader
    {
        return new EagerLoader($this->getEntityManager(), $this->getEntityClass());
    }

    protected function configureListQueryBuilder(QueryBuilder $qb, ListQuery $query): void
    {
    }

    /**
     * Total of the list without the eager loads and the sort: a plain COUNT, DISTINCT only
     * when a filter joins a collection. Null when the list query groups its rows
     * (GROUP BY / HAVING from configureListQueryBuilder()): the Doctrine paginator counts then.
     */
    protected function countForList(ListQuery $query): ?int
    {
        $qb = $this->createQueryBuilder(self::ALIAS);
        $helper = new QueryBuilderHelper($qb, self::ALIAS, $this->getEntityManager());

        $this->configureListQueryBuilder($qb, $query);
        $this->applyItem($helper, $query->filters);
        $this->applySearch($helper, $query->filters);
        $this->applyFilters($helper, $query->filters);

        if ([] !== $qb->getDQLPart('groupBy') || null !== $qb->getDQLPart('having')) {
            return null;
        }

        $identifier = self::ALIAS . '.' . $this->getClassMetadata()->getSingleIdentifierFieldName();
        $qb->select(\sprintf($helper->joinsCollection() ? 'COUNT(DISTINCT %s)' : 'COUNT(%s)', $identifier))
            ->resetDQLPart('orderBy');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param T                         $entity
     * @param FormInterface<mixed>|null $form
     */
    protected function beforeSave(object $entity, ?FormInterface $form): void
    {
    }

    /**
     * @param T                         $entity
     * @param FormInterface<mixed>|null $form
     */
    protected function afterSave(object $entity, ?FormInterface $form): void
    {
    }

    /**
     * @param T $entity
     */
    protected function beforeRemove(object $entity): void
    {
    }

    /**
     * @param array<string, mixed> $filters
     */
    protected function applyItem(QueryBuilderHelper $helper, array $filters): void
    {
        if (!\array_key_exists(ListQuery::ID_FILTER, $filters) || !\is_scalar($filters[ListQuery::ID_FILTER])) {
            return;
        }

        $identifier = $this->getClassMetadata()->getSingleIdentifierFieldName();

        $helper->getQueryBuilder()->andWhere(
            \sprintf(
                '%s.%s = %s',
                self::ALIAS,
                $identifier,
                $helper->parameter($filters[ListQuery::ID_FILTER]),
            ),
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    protected function applySearch(QueryBuilderHelper $helper, array $filters): void
    {
        $entityClass = $this->getEntityClass();
        $search = $filters[ListQuery::SEARCH_FILTER] ?? null;

        if (!is_subclass_of($entityClass, SearchableInterface::class) || !\is_string($search) || '' === trim($search)) {
            return;
        }

        $qb = $helper->getQueryBuilder();
        $pattern = '%' . addcslashes(mb_strtolower(trim($search)), '%_\\') . '%';
        $conditions = [];
        $placeholder = null;

        foreach ($entityClass::getSearchableFields() as $path) {
            $field = $helper->resolveField($path);

            if (null === $field) {
                continue;
            }

            $placeholder ??= $helper->parameter($pattern);
            $conditions[] = \sprintf('LOWER(%s) LIKE %s', $field, $placeholder);
        }

        if ([] !== $conditions) {
            $qb->andWhere($qb->expr()->orX(...$conditions));
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    protected function applyFilters(QueryBuilderHelper $helper, array $filters): void
    {
        $entityClass = $this->getEntityClass();

        if (!is_subclass_of($entityClass, FilterableInterface::class)) {
            return;
        }

        $configs = $entityClass::getFilters();
        $registry = $this->filterHandlers ??= FilterHandlerRegistry::withDefaults();

        foreach ($filters as $property => $value) {
            if (!isset($configs[$property]['type'])) {
                continue;
            }

            $registry->get($configs[$property]['type'])?->apply($helper, $property, $value, $configs[$property]);
        }
    }

    protected function applySort(QueryBuilderHelper $helper, ListQuery $query): void
    {
        $qb = $helper->getQueryBuilder();

        if (null !== $query->sortBy && is_subclass_of($this->getEntityClass(), SortableInterface::class)) {
            $field = $helper->resolveField($query->sortBy);

            if (null !== $field) {
                $qb->addOrderBy($field, ListQuery::SORT_DESC === $query->sort ? \SortDirection::Descending : \SortDirection::Ascending);
            }
        }

        // Stable pagination: always end with the identifier.
        $qb->addOrderBy(self::ALIAS . '.' . $this->getClassMetadata()->getSingleIdentifierFieldName(), \SortDirection::Ascending);
    }
}
