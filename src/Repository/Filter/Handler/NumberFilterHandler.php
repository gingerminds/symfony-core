<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Filter\Handler;

use Gingerminds\CoreBundle\Repository\Filter\FilterHandlerInterface;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;

/**
 * `{from: number, to: number}`, both bounds optional and inclusive. `0` is a valid bound.
 */
final class NumberFilterHandler implements FilterHandlerInterface
{
    public function apply(QueryBuilderHelper $query, string $property, mixed $value, array $config): void
    {
        $field = $query->resolveField($property);

        if (null === $field || !\is_array($value)) {
            return;
        }

        $qb = $query->getQueryBuilder();

        if (isset($value['from']) && is_numeric($value['from'])) {
            $qb->andWhere(\sprintf('%s >= %s', $field, $query->parameter((float) $value['from'])));
        }

        if (isset($value['to']) && is_numeric($value['to'])) {
            $qb->andWhere(\sprintf('%s <= %s', $field, $query->parameter((float) $value['to'])));
        }
    }
}
