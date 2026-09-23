<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Cache;

interface CacheContextResolverInterface
{
    /**
     * @return array<string, int|string|null>
     */
    public function resolve(): array;
}
