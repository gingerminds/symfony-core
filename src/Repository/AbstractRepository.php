<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository;

use ApiPlatform\Doctrine\Orm\Extension\ParameterExtension;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Parameters;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\ApiPlatform\Filter\FilterParameters;
use Gingerminds\CoreBundle\Pagination\Paginator;
use Gingerminds\CoreBundle\Repository\Query\EagerLoader;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;
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

    private ?ParameterExtension $parameterExtension = null;

    /**
     * @param class-string<T> $entityClass
     */
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, $entityClass);
    }

    /**
     * The API Platform extension applying the list parameters, the one of the API collections.
     */
    #[Required]
    public function setParameterExtension(ParameterExtension $parameterExtension): void
    {
        $this->parameterExtension = $parameterExtension;
    }

    public function paginate(Parameters $parameters, int $page = 1, ?int $itemsPerPage = null): Paginator
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, min($this->maxItemsPerPage, $itemsPerPage ?? $this->itemsPerPage));
        $offset = ($page - 1) * $itemsPerPage;
        // Collection eager loads are loaded after the page (EagerLoader::loadCollections()).
        $qb = $this->buildListQueryBuilder(eagerCollections: false);
        $this->applyParameters($qb, $parameters);
        // Stable pagination: always end with the identifier.
        $qb->addOrderBy(self::ALIAS . '.' . $this->getClassMetadata()->getSingleIdentifierFieldName(), \SortDirection::Ascending)
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
            : $this->countForList($parameters) ?? \count($doctrinePaginator);

        return new Paginator($items, $total, $page, $itemsPerPage);
    }

    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->buildListQueryBuilder(eagerCollections: true);
    }

    public function applyParameters(QueryBuilder $qb, Parameters $parameters): void
    {
        $extension = $this->parameterExtension
            ?? throw new \LogicException(\sprintf('"%s" needs the API Platform ParameterExtension (setParameterExtension(), autowired on a service repository).', static::class));
        $entityClass = $this->getClassName();

        $extension->applyToCollection($qb, new QueryNameGenerator(), $entityClass, new GetCollection(class: $entityClass, parameters: $parameters));
    }

    /**
     * @param bool|null $eagerCollections null: no eager load
     */
    private function buildListQueryBuilder(?bool $eagerCollections): QueryBuilder
    {
        $qb = $this->createQueryBuilder(self::ALIAS);

        if (null !== $eagerCollections) {
            $this->createEagerLoader()->apply(new QueryBuilderHelper($qb, self::ALIAS, $this->getEntityManager()), $eagerCollections);
        }

        $this->configureListQueryBuilder($qb);

        return $qb;
    }

    public function createScopedQueryBuilder(): QueryBuilder
    {
        return $this->buildListQueryBuilder(eagerCollections: null);
    }

    public function findOneForRead(int|string $id): ?object
    {
        $qb = $this->createListQueryBuilder();
        $qb->andWhere(\sprintf('%s.%s = :gm_id', self::ALIAS, $this->getClassMetadata()->getSingleIdentifierFieldName()))
            ->setParameter('gm_id', $id);

        // No setMaxResults(): it would truncate fetch-joined collections.
        /** @var list<T> $result */
        $result = $qb->getQuery()->getResult();

        return $result[0] ?? null;
    }

    public function save(object $entity, bool $flush = true): void
    {
        if (!$flush) {
            $this->beforeSave($entity);
            $this->getEntityManager()->persist($entity);
            $this->afterSave($entity);

            return;
        }

        $this->saveWith($entity, function () use ($entity): void {
            $this->getEntityManager()->persist($entity);
            $this->getEntityManager()->flush();
        });
    }

    public function saveWith(object $entity, \Closure $write): mixed
    {
        $this->beforeSave($entity);
        $result = $write();
        $this->afterSave($entity);

        return $result;
    }

    public function remove(object $entity, bool $flush = true): void
    {
        if (!$flush) {
            $this->beforeRemove($entity);
            $this->getEntityManager()->remove($entity);

            return;
        }

        $this->removeWith($entity, function () use ($entity): void {
            $this->getEntityManager()->remove($entity);
            $this->getEntityManager()->flush();
        });
    }

    public function removeWith(object $entity, \Closure $write): mixed
    {
        $this->beforeRemove($entity);

        return $write();
    }

    private function createEagerLoader(): EagerLoader
    {
        return new EagerLoader($this->getEntityManager(), $this->getClassName());
    }

    /**
     * Joins and conditions every read needs (a scope, a context such as the current site): admin
     * lists and edit pages, API collections and items, facets. The root alias is `o` (ALIAS).
     */
    protected function configureListQueryBuilder(QueryBuilder $qb): void
    {
    }

    /**
     * Total of the list without the eager loads and the sorts: a plain COUNT, DISTINCT only
     * when a filter joins a collection. Null when the list query groups its rows
     * (GROUP BY / HAVING from configureListQueryBuilder()): the Doctrine paginator counts then.
     */
    protected function countForList(Parameters $parameters): ?int
    {
        $qb = $this->createScopedQueryBuilder();
        $this->applyParameters($qb, FilterParameters::without($parameters));

        if ([] !== $qb->getDQLPart('groupBy') || null !== $qb->getDQLPart('having')) {
            return null;
        }

        $helper = new QueryBuilderHelper($qb, self::ALIAS, $this->getEntityManager());
        $identifier = self::ALIAS . '.' . $this->getClassMetadata()->getSingleIdentifierFieldName();
        $qb->select(\sprintf($helper->joinsCollection() ? 'COUNT(DISTINCT %s)' : 'COUNT(%s)', $identifier))
            ->resetDQLPart('orderBy');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param T $entity
     */
    protected function beforeSave(object $entity): void
    {
    }

    /**
     * @param T $entity
     */
    protected function afterSave(object $entity): void
    {
    }

    /**
     * @param T $entity
     */
    protected function beforeRemove(object $entity): void
    {
    }
}
