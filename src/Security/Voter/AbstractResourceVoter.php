<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Voter;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
abstract class AbstractResourceVoter extends Voter
{
    public const string VIEW = 'VIEW';
    public const string CREATE = 'CREATE';
    public const string EDIT = 'EDIT';
    public const string DELETE = 'DELETE';

    private const array ATTRIBUTES = [self::VIEW, self::CREATE, self::EDIT, self::DELETE];

    abstract protected function getResourceName(): string;

    /**
     * @return class-string
     */
    abstract protected function getSubjectClass(): string;

    abstract protected function getPermissionName(): string;

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\in_array($attribute, self::ATTRIBUTES, true)) {
            return false;
        }

        if (\is_object($subject)) {
            return $subject instanceof ($this->getSubjectClass());
        }

        return \is_string($subject)
            && ($subject === $this->getResourceName() || is_a($subject, $this->getSubjectClass(), true));
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        $object = \is_object($subject) ? $subject : null;

        return match ($attribute) {
            self::VIEW => $this->canView($user, $object),
            self::CREATE => $this->canCreate($user),
            self::EDIT => $this->canEdit($user, $object),
            self::DELETE => $this->canDelete($user, $object),
            default => false,
        };
    }

    protected function canView(UserInterface $user, ?object $subject): bool
    {
        return $user->hasPermission('view ' . $this->getPermissionName());
    }

    protected function canCreate(UserInterface $user): bool
    {
        return $user->hasPermission('edit ' . $this->getPermissionName());
    }

    protected function canEdit(UserInterface $user, ?object $subject): bool
    {
        return $user->hasPermission('edit ' . $this->getPermissionName());
    }

    protected function canDelete(UserInterface $user, ?object $subject): bool
    {
        return $user->hasPermission('delete ' . $this->getPermissionName());
    }
}
