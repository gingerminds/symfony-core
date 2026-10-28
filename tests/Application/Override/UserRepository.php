<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\Override;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Repository\User\UserRepository as BaseUserRepository;

/**
 * Project repository of the overridden User: signs in by email or phone.
 */
final class UserRepository extends BaseUserRepository
{
    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        /** @var User|null $user */
        $user = $this->findOneBy(['phone' => $identifier]);

        return $user ?? parent::loadUserByIdentifier($identifier);
    }
}
