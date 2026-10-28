<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Doctrine\EventListener;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Hashes the plain password of a user into its password on flush, whoever set it (admin
 * form, API, command). An entity listener on `preFlush`, not `preUpdate`: the plain
 * password is not mapped, so changing only it leaves Doctrine no change to update.
 */
final readonly class UserPasswordListener
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function preFlush(UserInterface $user): void
    {
        $plainPassword = $user->getPlainPassword();

        if (null === $plainPassword || '' === $plainPassword) {
            return;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setPlainPassword(null);
    }
}
