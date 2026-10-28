<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Voter;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A permission name without subject (`is_granted('view dashboard')`): granted to a user holding
 * the permission. Permission names follow the `{action} {object}` lowercase convention, which
 * tells them apart from the uppercase attributes (`ROLE_*`, `IS_*`, `EDIT`...); the support is
 * cached by the access decision manager, per attribute and subject type.
 *
 * @extends Voter<string, null>
 */
final class PermissionNameVoter extends Voter
{
    private const string PATTERN = '/^[a-z][a-z0-9_-]* [a-z0-9_\- ]+$/';

    public function supportsAttribute(string $attribute): bool
    {
        return 1 === preg_match(self::PATTERN, $attribute);
    }

    public function supportsType(string $subjectType): bool
    {
        return 'null' === $subjectType;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return null === $subject && $this->supportsAttribute($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof UserInterface && $user->hasPermission($attribute);
    }
}
