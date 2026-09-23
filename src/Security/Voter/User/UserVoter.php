<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Voter\User;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;

class UserVoter extends AbstractResourceVoter
{
    protected function getResourceName(): string
    {
        return 'user';
    }

    protected function getSubjectClass(): string
    {
        return UserInterface::class;
    }

    protected function getPermissionName(): string
    {
        return 'users';
    }

    protected function canView(UserInterface $user, ?object $subject): bool
    {
        return parent::canView($user, $subject) || $this->isSelf($user, $subject);
    }

    protected function canEdit(UserInterface $user, ?object $subject): bool
    {
        return parent::canEdit($user, $subject) || $this->isSelf($user, $subject);
    }

    /**
     * Nobody deletes their own account from the admin.
     */
    protected function canDelete(UserInterface $user, ?object $subject): bool
    {
        return parent::canDelete($user, $subject) && !$this->isSelf($user, $subject);
    }

    private function isSelf(UserInterface $user, ?object $subject): bool
    {
        return $subject instanceof UserInterface && null !== $user->getId() && $subject->getId() === $user->getId();
    }
}
