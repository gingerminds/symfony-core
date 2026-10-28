<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

/**
 * An entity whose writes invalidate the whole cache of other resources (`#[Cacheable]` keys):
 * translation rows, child rows embedded in a parent's response. Read from the class or its
 * parents (CacheMetadata), the closest declaration winning.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class CacheCascade
{
    /**
     * @param list<string> $keys
     */
    public function __construct(
        public array $keys,
    ) {
    }
}
