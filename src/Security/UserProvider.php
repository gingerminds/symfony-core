<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Repository\User\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface as SecurityUserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<UserInterface>
 */
final readonly class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->users->loadUserByIdentifier($identifier);

        if (!$user instanceof UserInterface) {
            $exception = new UserNotFoundException(\sprintf('User "%s" not found.', $identifier));
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return $user;
    }

    public function refreshUser(SecurityUserInterface $user): UserInterface
    {
        if (!$this->supportsClass($user::class) || !$user instanceof UserInterface || null === $user->getId()) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $refreshed = $this->users->findForAuthentication($user->getId());

        if (!$refreshed instanceof UserInterface) {
            $exception = new UserNotFoundException(\sprintf('User with id "%s" not found.', $user->getId()));
            $exception->setUserIdentifier($user->getUserIdentifier());

            throw $exception;
        }

        return $refreshed;
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, $this->users->getEntityClass(), true);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        $this->users->upgradePassword($user, $newHashedPassword);
    }
}
