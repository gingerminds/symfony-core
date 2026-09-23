<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Filter\Handler;

use Gingerminds\CoreBundle\Repository\Filter\FilterHandlerInterface;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;

/**
 * `yes` / `no` (also `1`/`0`, `true`/`false`); `all` or empty disables the filter.
 * `no` also matches NULL.
 */
final class BooleanFilterHandler implements FilterHandlerInterface
{
    public function apply(QueryBuilderHelper $query, string $property, mixed $value, array $config): void
    {
        $field = $query->resolveField($property);

        if (null === $field || !\is_scalar($value)) {
            return;
        }

        $value = strtolower((string) $value);
        $qb = $query->getQueryBuilder();

        if (\in_array($value, ['yes', '1', 'true'], true)) {
            $qb->andWhere(\sprintf('%s = %s', $field, $query->parameter(true)));
        } elseif (\in_array($value, ['no', '0', 'false'], true)) {
            $qb->andWhere($qb->expr()->orX(
                \sprintf('%s = %s', $field, $query->parameter(false)),
                $qb->expr()->isNull($field),
            ));
        }
    }
}
