<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Voter\Role;

use Gingerminds\CoreBundle\Entity\Role\RoleInterface;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;

class RoleVoter extends AbstractResourceVoter
{
    public const string MANAGE_ROLES = 'manage roles';

    protected function getResourceName(): string
    {
        return 'role';
    }

    protected function getSubjectClass(): string
    {
        return RoleInterface::class;
    }

    protected function getPermissionName(): string
    {
        return 'roles';
    }

    protected function canView(UserInterface $user, ?object $subject): bool
    {
        return $user->hasPermission(self::MANAGE_ROLES);
    }

    protected function canCreate(UserInterface $user): bool
    {
        return $user->hasPermission(self::MANAGE_ROLES);
    }

    protected function canEdit(UserInterface $user, ?object $subject): bool
    {
        return $user->hasPermission(self::MANAGE_ROLES);
    }

    protected function canDelete(UserInterface $user, ?object $subject): bool
    {
        return $user->hasPermission(self::MANAGE_ROLES);
    }
}
