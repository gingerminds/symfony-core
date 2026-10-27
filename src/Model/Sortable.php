<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

/**
 * A property the lists may sort on (`?sortBy=`, admin column headers); any other path is ignored.
 * On an association, `paths` are the sortable fields of the related entity:
 * `#[Sortable(paths: ['lastname'])]` on `$contributor` allows `contributor.lastname`.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Sortable
{
    /**
     * @param list<string> $paths
     */
    public function __construct(
        public array $paths = [],
    ) {
    }
}
