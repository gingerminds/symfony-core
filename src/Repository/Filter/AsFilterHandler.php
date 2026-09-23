<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Filter;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class AsFilterHandler
{
    public function __construct(
        public string $type,
    ) {
    }
}
