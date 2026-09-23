<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Voter;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class SuperAdminVoter implements VoterInterface, CacheableVoterInterface
{
    public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        $user = $token->getUser();

        if ($user instanceof UserInterface && $user->isSuperAdmin()) {
            $vote?->addReason('The user is a super-admin.');

            return self::ACCESS_GRANTED;
        }

        return self::ACCESS_ABSTAIN;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return true;
    }

    public function supportsType(string $subjectType): bool
    {
        return true;
    }
}
