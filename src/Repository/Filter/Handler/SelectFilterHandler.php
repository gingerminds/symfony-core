<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Filter\Handler;

use Gingerminds\CoreBundle\Repository\Filter\FilterHandlerInterface;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;

/**
 * Single value (`=`) or list (`IN`); `all` or empty disables the filter.
 */
final class SelectFilterHandler implements FilterHandlerInterface
{
    public function apply(QueryBuilderHelper $query, string $property, mixed $value, array $config): void
    {
        $field = $query->resolveField($property);
        $values = self::normalize($value);

        if (null === $field || [] === $values) {
            return;
        }

        $query->getQueryBuilder()->andWhere(\sprintf('%s IN (%s)', $field, $query->parameter($values)));
    }

    /**
     * @return list<scalar>
     */
    public static function normalize(mixed $value): array
    {
        $values = \is_array($value) ? $value : [$value];

        return array_values(array_filter(
            $values,
            static fn (mixed $item): bool => \is_scalar($item) && '' !== $item && 'all' !== $item,
        ));
    }
}
