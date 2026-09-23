<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model\Trait;

trait CacheableResourceTrait
{
    public static function getCacheTtl(): ?int
    {
        return null;
    }
}
