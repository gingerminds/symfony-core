<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Filter\Handler;

use Gingerminds\CoreBundle\Repository\Filter\FilterHandlerInterface;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;

final class SelectEnumFilterHandler implements FilterHandlerInterface
{
    public function apply(QueryBuilderHelper $query, string $property, mixed $value, array $config): void
    {
        $owner = $query->resolveOwner($property);
        $values = SelectFilterHandler::normalize($value);

        if (null === $owner || [] === $values || !$owner['metadata']->hasField($owner['name'])) {
            return;
        }

        $enumClass = $owner['metadata']->getFieldMapping($owner['name'])->enumType ?? null;

        if (null !== $enumClass && is_subclass_of($enumClass, \BackedEnum::class)) {
            $values = array_map(static fn (mixed $item): mixed => self::resolve($enumClass, (string) $item), $values);
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
