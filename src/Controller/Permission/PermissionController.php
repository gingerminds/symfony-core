<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\Permission;

use Gingerminds\CoreBundle\Controller\AbstractCrudController;

class PermissionController extends AbstractCrudController
{
    protected function getResourceName(): string
    {
        return 'permission';
    }
}
