<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

interface CacheCascadeInterface
{
    /**
     * @return list<string>
     */
    public static function getCascadeCacheKeys(): array;
}
