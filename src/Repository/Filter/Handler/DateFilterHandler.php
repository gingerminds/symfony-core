<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Filter\Handler;

use Doctrine\DBAL\Types\Types;
use Gingerminds\CoreBundle\Repository\Filter\FilterHandlerInterface;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;
use Gingerminds\CoreBundle\Service\DisplayTimezone;

/**
 * `{from: 'Y-m-d', to: 'Y-m-d'}`, both bounds optional and inclusive (whole days of the display timezone).
 */
final readonly class DateFilterHandler implements FilterHandlerInterface
{
    /**
     * Calendar days (no time, no timezone): compared as is.
     */
    private const array DAY_TYPES = [Types::DATE_MUTABLE, Types::DATE_IMMUTABLE, 'day_point'];

    public function __construct(
        private DisplayTimezone $displayTimezone = new DisplayTimezone(),
    ) {
    }

    public function apply(QueryBuilderHelper $query, string $property, mixed $value, array $config): void
    {
        if (!\is_array($value)) {
            return;
        }

        $owner = $query->resolveOwner($property);

        if (null === $owner || !$owner['metadata']->hasField($owner['name'])) {
            return;
        }

        $calendarDay = \in_array($owner['metadata']->getTypeOfField($owner['name']), self::DAY_TYPES, true);
        $this->applyRange($query, $owner['alias'] . '.' . $owner['name'], $value, $calendarDay);
    }

    /**
     * @param array<mixed> $value
     * @param bool         $calendarDay the column holds a day (`date`), not a UTC date-time
     */
    public function applyRange(QueryBuilderHelper $query, string $field, array $value, bool $calendarDay = false): void
    {
        [$from, $to] = $calendarDay
            ? $this->displayTimezone->dayRange($value['from'] ?? null, $value['to'] ?? null)
            : $this->displayTimezone->dayRangeToUtc($value['from'] ?? null, $value['to'] ?? null);
        $qb = $query->getQueryBuilder();

        if ($from instanceof \DateTimeImmutable) {
            $qb->andWhere(\sprintf('%s >= %s', $field, $query->parameter($from)));
        }

        if ($to instanceof \DateTimeImmutable) {
            $qb->andWhere(\sprintf('%s <= %s', $field, $query->parameter($to)));
        }
    }
}
