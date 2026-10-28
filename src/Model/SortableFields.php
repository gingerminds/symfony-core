<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

/**
 * The paths an entity may be sorted on, read from its `#[Sortable]` properties (parents included).
 */
final class SortableFields
{
    /**
     * @param class-string $class
     *
     * @return list<string>
     */
    public static function of(string $class): array
    {
        return PropertyPaths::of($class, Sortable::class);
    }

    /**
     * @param class-string $class
     */
    public static function allows(string $class, string $path): bool
    {
        return \in_array($path, self::of($class), true);
    }
}
