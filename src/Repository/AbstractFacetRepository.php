<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository;

use Doctrine\ORM\QueryBuilder;
use Gingerminds\CoreBundle\Model\FilterableInterface;
use Gingerminds\CoreBundle\Repository\Facet\DateFacetCalculator;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;

/**
 * @template T of FilterableInterface
 *
 * @extends AbstractRepository<T>
 */
abstract class AbstractFacetRepository extends AbstractRepository
{
    protected const string COUNT_TOTAL = 'COUNT(DISTINCT %s) AS total';

    /**
     * @param list<string> $excludedKeys
     */
    public function buildFacetedQuery(ListQuery $query, array $excludedKeys = []): QueryBuilder
    {
        $qb = $this->createListQueryBuilder($query->withSort(null)->withoutPagination(), $excludedKeys)
            ->select(self::ALIAS)
            ->resetDQLPart('orderBy');

        $this->extraFacetQuerySetup($qb);

        return $qb;
    }

    /**
     * @return array{min: ?string, max: ?string, years: list<int>}
     */
    public function getDateFacetStats(ListQuery $query, string $property): array
    {
        $qb = $this->buildFacetedQuery($query, [$property]);
        $field = new QueryBuilderHelper($qb, self::ALIAS, $this->getEntityManager())->resolveField($property);

        if (null === $field) {
            return ['min' => null, 'max' => null, 'years' => []];
        }

        return DateFacetCalculator::compute($qb, $field);
    }

    /**
     * @return array<int|string, int>
     */
    public function getAssociationFacetCounts(ListQuery $query, string $association): array
    {
        $qb = $this->buildFacetedQuery($query, [$association]);
        $helper = new QueryBuilderHelper($qb, self::ALIAS, $this->getEntityManager());
        $alias = $helper->join(self::ALIAS, $association);

        /** @var list<array{id: int|string, total: int|string}> $rows */
        $rows = $qb
            ->select(\sprintf('%s.id AS id', $alias), \sprintf(self::COUNT_TOTAL, self::ALIAS . '.id'))
            ->andWhere($qb->expr()->isNotNull($alias . '.id'))
            ->groupBy($alias . '.id')
            ->getQuery()
            ->getArrayResult();

        $counts = [];

        foreach ($rows as $row) {
            $counts[$row['id']] = (int) $row['total'];
        }

        return $counts;
    }

    protected function extraFacetQuerySetup(QueryBuilder $query): void
    {
    }
}
