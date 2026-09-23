<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

/**
 * @phpstan-type FilterConfig array{
 *     type: string,
 *     label?: string,
 *     choices?: array<int|string, string>,
 *     multiple?: bool,
 *     entity?: class-string,
 *     resource?: string,
 *     disabled_for_back?: bool,
 * }
 */
interface FilterableInterface
{
    /**
     * @return array<string, FilterConfig>
     */
    public static function getFilters(): array;
}
