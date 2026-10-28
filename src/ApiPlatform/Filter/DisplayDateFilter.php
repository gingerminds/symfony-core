<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Filter;

use ApiPlatform\Metadata\JsonSchemaFilterInterface;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use Doctrine\DBAL\Types\Types;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;
use Gingerminds\CoreBundle\Service\DisplayTimezone;

/**
 * Whole days of the display timezone, both bounds optional and inclusive:
 * `publishedAt[after]=2024-01-01&publishedAt[before]=2024-12-31` (`Y-m-d`, the bound names of the
 * API Platform DateFilter). A date-time column is stored in UTC: the bounds are the UTC instants
 * of the start and end of those days; a `date` column is compared as is.
 */
final class DisplayDateFilter extends AbstractPropertyFilter implements JsonSchemaFilterInterface
{
    public const string AFTER = 'after';
    public const string BEFORE = 'before';

    /**
     * Calendar days (no time, no timezone): compared as is.
     */
    private const array DAY_TYPES = [Types::DATE_MUTABLE, Types::DATE_IMMUTABLE, 'day_point'];

    public function __construct(
        private readonly DisplayTimezone $displayTimezone = new DisplayTimezone(),
    ) {
    }

    public function getSchema(Parameter $parameter): array
    {
        return [
            'type' => 'object',
            'properties' => [
                self::AFTER => ['type' => 'string', 'format' => 'date'],
                self::BEFORE => ['type' => 'string', 'format' => 'date'],
            ],
        ];
    }

    public function getOpenApiParameters(Parameter $parameter): array
    {
        $key = (string) $parameter->getKey();
        $schema = ['type' => 'string', 'format' => 'date'];

        return [
            new OpenApiParameter(name: $key . '[' . self::AFTER . ']', in: 'query', description: 'From this day (Y-m-d), included', schema: $schema),
            new OpenApiParameter(name: $key . '[' . self::BEFORE . ']', in: 'query', description: 'Until this day (Y-m-d), included', schema: $schema),
        ];
    }

    protected function filter(QueryBuilderHelper $query, string $property, mixed $value, Parameter $parameter): void
    {
        $owner = $query->resolveOwner($property);

        if (!\is_array($value) || null === $owner || !$owner['metadata']->hasField($owner['name'])) {
            return;
        }

        $calendarDay = \in_array($owner['metadata']->getTypeOfField($owner['name']), self::DAY_TYPES, true);
        [$after, $before] = $calendarDay
            ? $this->displayTimezone->dayRange($value[self::AFTER] ?? null, $value[self::BEFORE] ?? null)
            : $this->displayTimezone->dayRangeToUtc($value[self::AFTER] ?? null, $value[self::BEFORE] ?? null);
        $field = $owner['alias'] . '.' . $owner['name'];
        $qb = $query->getQueryBuilder();

        if ($after instanceof \DateTimeImmutable) {
            $qb->andWhere(\sprintf('%s >= %s', $field, $query->parameter($after)));
        }

        if ($before instanceof \DateTimeImmutable) {
            $qb->andWhere(\sprintf('%s <= %s', $field, $query->parameter($before)));
        }
    }
}
