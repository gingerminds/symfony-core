<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Facet;

use Doctrine\ORM\QueryBuilder;

final class DateFacetCalculator
{
    /**
     * @param string $field DQL path (`o.publishedAt`)
     *
     * @return array{min: ?string, max: ?string, years: list<int>}
     */
    public static function compute(QueryBuilder $query, string $field): array
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
            static fn (mixed $value): int => (int) self::toDate($value)?->format('Y'),
            $values,
        )));
        rsort($years);

        return [
            'min' => self::toDate($bounds['min'] ?? null)?->format('Y-m-d'),
            'max' => self::toDate($bounds['max'] ?? null)?->format('Y-m-d'),
            'years' => $years,
        ];
    }

    private static function toDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        try {
            return \is_string($value) && '' !== $value ? new \DateTimeImmutable($value) : null;
        } catch (\Exception) {
            return null;
        }
    }
}
