<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Voter;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, null>
 */
final class PermissionNameVoter extends Voter
{
    private const string PATTERN = '/^[a-z][a-z0-9_-]* [a-z0-9_\- ]+$/';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return null === $subject && 1 === preg_match(self::PATTERN, $attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof UserInterface && $user->hasPermission($attribute);
    }
}
