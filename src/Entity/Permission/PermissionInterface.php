<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Entity\Permission;

use Gingerminds\CoreBundle\Model\ResourceInterface;

interface PermissionInterface extends ResourceInterface
{
    /**
     * Core permissions, created by gingerminds:permissions:sync.
     */
    public const string ACCESS_ADMIN = 'access admin';
    public const string VIEW_DASHBOARD = 'view dashboard';
    public const string VIEW_SETTINGS = 'view settings';
    public const string MANAGE_ROLES = 'manage roles';

    public function getName(): ?string;

    public function setName(string $name): void;
}
