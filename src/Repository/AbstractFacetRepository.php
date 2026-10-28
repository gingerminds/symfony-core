<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository;

use ApiPlatform\Metadata\Parameters;
use Doctrine\ORM\QueryBuilder;
use Gingerminds\CoreBundle\ApiPlatform\Filter\FilterParameters;
use Gingerminds\CoreBundle\Repository\Facet\DateFacetCalculator;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;
use Gingerminds\CoreBundle\Service\DisplayTimezone;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Facets of a list: each one computed against every other active list parameter (the
 * collection operation's parameters on the API, FilterParameters::fromQuery() on the admin),
 * not its own.
 *
 * @template T of object
 *
 * @extends AbstractRepository<T>
 */
abstract class AbstractFacetRepository extends AbstractRepository
{
    protected const string COUNT_TOTAL = 'COUNT(DISTINCT %s) AS total';

    private ?DisplayTimezone $displayTimezone = null;

    #[Required]
    public function setDisplayTimezone(DisplayTimezone $displayTimezone): void
    {
        $this->displayTimezone = $displayTimezone;
    }

    /**
     * The list query (context scope) with the parameters but those of $excludedKeys and the
     * sorts, without eager loads.
     *
     * @param list<string> $excludedKeys
     */
    public function buildFacetedQuery(Parameters $parameters, array $excludedKeys = []): QueryBuilder
    {
        $qb = $this->createScopedQueryBuilder();
        $this->applyParameters($qb, FilterParameters::without($parameters, $excludedKeys));
        $this->extraFacetQuerySetup($qb);

        return $qb;
    }

    /**
     * @return array{min: ?string, max: ?string, years: list<int>}
     */
    public function getDateFacetStats(Parameters $parameters, string $property): array
    {
        $qb = $this->buildFacetedQuery($parameters, [$property]);
        $field = new QueryBuilderHelper($qb, self::ALIAS, $this->getEntityManager())->resolveField($property);

        if (null === $field) {
            return ['min' => null, 'max' => null, 'years' => []];
        }

        return DateFacetCalculator::compute($qb, $field, $this->displayTimezone ??= new DisplayTimezone());
    }

    /**
     * @return array<int|string, int> related id => rows
     */
    public function getAssociationFacetCounts(Parameters $parameters, string $association): array
    {
        $qb = $this->buildFacetedQuery($parameters, [$association]);
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
