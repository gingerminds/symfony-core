<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

interface EagerLoadableInterface
{
    /**
     * Association paths, `relation` or `relation.subRelation`.
     *
     * @return list<string>
     */
    public static function getEagerLoads(): array;
}
