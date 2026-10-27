<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

/**
 * An association loaded with the entities on every repository read (lists, API reads, admin
 * edit page): no query per row. `paths` load associations of the related entity along with
 * it: `#[EagerLoad(paths: ['language'])]` on `$translations` loads `translations.language`.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class EagerLoad
{
    /**
     * @param list<string> $paths
     */
    public function __construct(
        public array $paths = [],
    ) {
    }
}
