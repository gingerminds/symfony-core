<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\User;

use Gingerminds\CoreBundle\Controller\AbstractCrudController;

class ContributorController extends AbstractCrudController
{
    protected function getResourceName(): string
    {
        return 'contributor';
    }
}
