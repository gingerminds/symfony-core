<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Cache;

final class NullCacheContextResolver implements CacheContextResolverInterface
{
    public function resolve(): array
    {
        return [];
    }
}
