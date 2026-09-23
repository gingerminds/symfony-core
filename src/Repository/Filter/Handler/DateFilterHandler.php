<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Filter\Handler;

use Gingerminds\CoreBundle\Repository\Filter\FilterHandlerInterface;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;

/**
 * `{from: 'Y-m-d', to: 'Y-m-d'}`, both bounds optional and inclusive (whole days).
 */
final class DateFilterHandler implements FilterHandlerInterface
{
    public function apply(QueryBuilderHelper $query, string $property, mixed $value, array $config): void
    {
        $field = $query->resolveField($property);

        if (null === $field || !\is_array($value)) {
            return;
        }

        self::applyRange($query, $field, $value);
    }

    /**
     * Shared with the faceted search (AbstractFacetRepository).
     *
     * @param array<mixed> $value
     */
    public static function applyRange(QueryBuilderHelper $query, string $field, array $value): void
    {
        $from = self::parse($value['from'] ?? null)?->setTime(0, 0);
        $to = self::parse($value['to'] ?? null)?->setTime(23, 59, 59);
        $qb = $query->getQueryBuilder();

        if (null !== $from) {
            $qb->andWhere(\sprintf('%s >= %s', $field, $query->parameter($from)));
        }

        if (null !== $to) {
            $qb->andWhere(\sprintf('%s <= %s', $field, $query->parameter($to)));
        }
    }

    private static function parse(mixed $date): ?\DateTimeImmutable
    {
        if (!\is_string($date) || '' === $date) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return false === $parsed ? null : $parsed;
    }
}
