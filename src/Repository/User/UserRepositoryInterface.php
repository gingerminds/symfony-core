<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\User;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * The repository of the configured user entity (`gingerminds_core.repository.user`): the
 * `repositoryClass` of a project entity is used by the authentication and the forms too.
 *
 * @extends RepositoryInterface<UserInterface>
 */
interface UserRepositoryInterface extends RepositoryInterface, UserLoaderInterface, PasswordUpgraderInterface
{
    public function loadUserByIdentifier(string $identifier): ?UserInterface;

    /**
     * The user refreshed from the session, with what the security needs (roles, permissions, contributor).
     */
    public function findForAuthentication(int|string $id): ?UserInterface;
}
