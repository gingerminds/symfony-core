<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Filter;

use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;

interface FilterHandlerInterface
{
    /**
     * @param array<string, mixed> $config the entry declared in getFilters()
     */
    public function apply(QueryBuilderHelper $query, string $property, mixed $value, array $config): void;
}
