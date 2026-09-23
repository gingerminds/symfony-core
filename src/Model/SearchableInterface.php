<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

interface SearchableInterface
{
    /**
     * Fields matched by `filters[search]`. Supports `relation.field` notation.
     *
     * @return list<string>
     */
    public static function getSearchableFields(): array;
}
