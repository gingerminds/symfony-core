<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

/**
 * The paths `filters[search]` matches on an entity, read from its `#[Searchable]` properties
 * (parents included); none: the entity is not searchable.
 */
final class SearchableFields
{
    /**
     * @param class-string $class
     *
     * @return list<string>
     */
    public static function of(string $class): array
    {
        return PropertyPaths::of($class, Searchable::class);
    }
}
