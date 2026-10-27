<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

/**
 * A property matched by `filters[search]` (case-insensitive `LIKE`), any other is not searched.
 * On an association, `paths` are the searched fields of the related entity:
 * `#[Searchable(paths: ['name'])]` on `$category` searches `category.name`.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Searchable
{
    /**
     * @param list<string> $paths
     */
    public function __construct(
        public array $paths = [],
    ) {
    }
}
