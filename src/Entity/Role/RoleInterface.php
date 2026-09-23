<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Entity\Role;

use Doctrine\Common\Collections\Collection;
use Gingerminds\CoreBundle\Entity\Permission\PermissionInterface;
use Gingerminds\CoreBundle\Model\ResourceInterface;

interface RoleInterface extends ResourceInterface
{
    public function getName(): ?string;

    public function setName(string $name): void;

    public function isExternal(): bool;

    public function setExternal(bool $external): void;

    public function isDefault(): bool;

    public function setDefault(bool $default): void;

    /**
     * @return Collection<int, PermissionInterface>
     */
    public function getPermissions(): Collection;

    public function addPermission(PermissionInterface $permission): void;

    public function removePermission(PermissionInterface $permission): void;

    public function hasPermission(string $permission): bool;

    public function getSecurityRole(): string;
}
