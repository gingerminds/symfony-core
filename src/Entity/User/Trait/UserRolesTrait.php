<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Entity\User\Trait;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Entity\Role\RoleInterface;
use Gingerminds\CoreBundle\Entity\User\BaseUser;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Roles and permissions of a user: the role entities it holds and the Symfony
 * roles / permission names derived from them. $roleEntities is initialized by
 * the BaseUser constructor.
 */
trait UserRolesTrait
{
    /**
     * @var Collection<int, RoleInterface>
     */
    #[ORM\ManyToMany(targetEntity: RoleInterface::class)]
    #[ORM\JoinTable(name: 'user_roles')]
    #[ORM\JoinColumn(name: 'user_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'role_id', onDelete: 'CASCADE')]
    #[Groups([BaseUser::GROUP_LIST, BaseUser::GROUP_READ])]
    #[SerializedName('roles')]
    protected Collection $roleEntities;

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        foreach ($this->roleEntities as $role) {
            $roles[] = $role->getSecurityRole();
        }

        return array_values(array_unique($roles));
    }

    /**
     * @return Collection<int, RoleInterface>
     */
    public function getRoleEntities(): Collection
    {
        return $this->roleEntities;
    }

    public function addRoleEntity(RoleInterface $role): void
    {
        if (!$this->roleEntities->contains($role)) {
            $this->roleEntities->add($role);
        }
    }

    public function removeRoleEntity(RoleInterface $role): void
    {
        $this->roleEntities->removeElement($role);
    }

    public function hasRole(string $roleName): bool
    {
        foreach ($this->roleEntities as $role) {
            if ($role->getName() === $roleName) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserInterface::SUPER_ADMIN_ROLE);
    }

    /**
     * @return list<string>
     */
    public function getPermissionNames(): array
    {
        $names = [];

        foreach ($this->roleEntities as $role) {
            foreach ($role->getPermissions() as $permission) {
                $names[] = (string) $permission->getName();
            }
        }

        return array_values(array_unique($names));
    }

    public function hasPermission(string $permission): bool
    {
        return \in_array($permission, $this->getPermissionNames(), true);
    }
}
