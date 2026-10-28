<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Filter;

use ApiPlatform\Metadata\Parameter;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;

/**
 * A backed enum column, by case value or case name (case-insensitive): `status=published`,
 * `status[]=draft&status[]=Archived`. The API Platform BackedEnumFilter only takes the values.
 */
final class EnumFilter extends AbstractPropertyFilter
{
    /**
     * @return list<OpenApiParameter>
     */
    public function getOpenApiParameters(Parameter $parameter): array
    {
        return self::listOpenApiParameters($parameter, 'A case value or name, or a list of them');
    }

    protected function filter(QueryBuilderHelper $query, string $property, mixed $value, Parameter $parameter): void
    {
        $owner = $query->resolveOwner($property);
        $values = self::values($value);

        if (null === $owner || [] === $values || !$owner['metadata']->hasField($owner['name'])) {
            return;
        }

        $enumClass = $owner['metadata']->getFieldMapping($owner['name'])->enumType ?? null;

        if (null !== $enumClass && is_subclass_of($enumClass, \BackedEnum::class)) {
            $values = array_map(static fn (int|string|float|bool $item): int|string => self::resolve($enumClass, (string) $item), $values);
        }

        $query->getQueryBuilder()->andWhere(
            \sprintf('%s.%s IN (%s)', $owner['alias'], $owner['name'], $query->parameter($values)),
        );
    }

    /**
     * @param class-string<\BackedEnum> $enumClass
     */
    private static function resolve(string $enumClass, string $value): int|string
    {
        foreach ($enumClass::cases() as $case) {
            if (0 === strcasecmp((string) $case->value, $value) || 0 === strcasecmp($case->name, $value)) {
                return $case->value;
            }
        }

        return $value;
    }
}
