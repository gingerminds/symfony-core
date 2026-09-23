<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Menu;

interface AdminMenuProviderInterface
{
    /**
     * @return iterable<MenuItem>
     */
    public function getItems(): iterable;
}
