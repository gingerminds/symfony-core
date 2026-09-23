<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Entity\User;

use Doctrine\Common\Collections\Collection;
use Gingerminds\CoreBundle\Entity\Role\RoleInterface;
use Gingerminds\CoreBundle\Model\ResourceInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface as SecurityUserInterface;

interface UserInterface extends SecurityUserInterface, PasswordAuthenticatedUserInterface, ResourceInterface
{
    public const string SUPER_ADMIN_ROLE = 'Super-Admin';

    public function getEmail(): ?string;

    public function setEmail(string $email): void;

    public function setPassword(?string $password): void;

    public function getPlainPassword(): ?string;

    public function setPlainPassword(?string $plainPassword): void;

    /**
     * @return Collection<int, RoleInterface>
     */
    public function getRoleEntities(): Collection;

    public function addRoleEntity(RoleInterface $role): void;

    public function removeRoleEntity(RoleInterface $role): void;

    public function hasRole(string $roleName): bool;

    public function isSuperAdmin(): bool;

    /**
     * @return list<string>
     */
    public function getPermissionNames(): array;

    public function hasPermission(string $permission): bool;

    public function getContributor(): ?ContributorInterface;

    public function setContributor(?ContributorInterface $contributor): void;
}
