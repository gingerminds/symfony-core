<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Model\EagerLoadableInterface;
use Gingerminds\CoreBundle\Model\FilterableInterface;
use Gingerminds\CoreBundle\Model\SearchableInterface;
use Gingerminds\CoreBundle\Model\SortableInterface;
use Gingerminds\CoreBundle\Pagination\Paginator;
use Gingerminds\CoreBundle\Repository\Filter\FilterHandlerRegistry;
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
        $qb = $this->createListQueryBuilder($query)
            ->setFirstResult(($query->page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage);

        $doctrinePaginator = new DoctrinePaginator($qb, fetchJoinCollection: true);

        /** @var list<T> $items */
        $items = iterator_to_array($doctrinePaginator->getIterator(), false);

        return new Paginator($items, \count($doctrinePaginator), $query->page, $itemsPerPage);
    }

    public function findForList(ListQuery $query): array
    {
        /** @var list<T> */
        return $this->createListQueryBuilder($query)->getQuery()->getResult();
    }

    public function createListQueryBuilder(ListQuery $query, array $excludedFilters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder(self::ALIAS);
        $helper = new QueryBuilderHelper($qb, self::ALIAS, $this->getEntityManager());

        $this->applyEagerLoads($helper);
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

    protected function configureListQueryBuilder(QueryBuilder $qb, ListQuery $query): void
    {
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

    protected function applyEagerLoads(QueryBuilderHelper $helper): void
    {
        $entityClass = $this->getEntityClass();

        if (!is_subclass_of($entityClass, EagerLoadableInterface::class)) {
            return;
        }

        foreach ($entityClass::getEagerLoads() as $path) {
            $helper->eagerLoad($path);
        }
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
                $qb->addOrderBy($field, strtoupper($query->sort));
            }
        }

        // Stable pagination: always end with the identifier.
        $qb->addOrderBy(self::ALIAS . '.' . $this->getClassMetadata()->getSingleIdentifierFieldName(), 'ASC');
    }
}
