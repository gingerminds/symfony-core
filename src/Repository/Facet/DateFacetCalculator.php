<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Facet;

use Doctrine\ORM\QueryBuilder;
use Gingerminds\CoreBundle\Service\DisplayTimezone;

/**
 * Bounds and years are those of the display timezone (dates are stored in UTC).
 */
final class DateFacetCalculator
{
    /**
     * @param string $field DQL path (`o.publishedAt`)
     *
     * @return array{min: ?string, max: ?string, years: list<int>}
     */
    public static function compute(QueryBuilder $query, string $field, DisplayTimezone $displayTimezone = new DisplayTimezone()): array
    {
        $base = (clone $query)
            ->resetDQLPart('orderBy')
            ->setFirstResult(0)
            ->setMaxResults(null)
            ->andWhere($query->expr()->isNotNull($field));

        /** @var array{min: mixed, max: mixed}|null $bounds */
        $bounds = (clone $base)
            ->select(\sprintf('MIN(%1$s) AS min, MAX(%1$s) AS max', $field))
            ->getQuery()
            ->getOneOrNullResult();

        /** @var list<mixed> $values */
        $values = (clone $base)
            ->select(\sprintf('%s AS value', $field))
            ->distinct()
            ->getQuery()
            ->getSingleColumnResult();

        $years = array_values(array_unique(array_map(
            static fn (mixed $value): int => (int) $displayTimezone->toDisplay(self::toDate($value))?->format('Y'),
            $values,
        )));
        rsort($years);

        return [
            'min' => $displayTimezone->toDisplay(self::toDate($bounds['min'] ?? null))?->format('Y-m-d'),
            'max' => $displayTimezone->toDisplay(self::toDate($bounds['max'] ?? null))?->format('Y-m-d'),
            'years' => $years,
        ];
    }

    /**
     * Aggregates (MIN/MAX) come back as raw database strings: UTC.
     */
    private static function toDate(mixed $value): \DateTimeInterface|string|null
    {
        return $value instanceof \DateTimeInterface || \is_string($value) ? $value : null;
    }
}
