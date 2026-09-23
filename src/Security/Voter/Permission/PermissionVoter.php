<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Voter\Permission;

use Gingerminds\CoreBundle\Entity\Permission\PermissionInterface;
use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;

class PermissionVoter extends AbstractResourceVoter
{
    protected function getResourceName(): string
    {
        return 'permission';
    }

    protected function getSubjectClass(): string
    {
        return PermissionInterface::class;
    }

    protected function getPermissionName(): string
    {
        return 'permissions';
    }
}
