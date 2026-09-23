<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Entity\Permission;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Repository\Permission\PermissionRepository;

#[ORM\Entity(repositoryClass: PermissionRepository::class)]
#[ORM\Table(name: 'permissions')]
#[ApiResource(
    shortName: 'Permission',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => [BasePermission::GROUP_LIST]],
            security: "is_granted('VIEW', 'permission')",
        ),
        new Get(security: "is_granted('VIEW', object)"),
        new Post(
            security: "is_granted('CREATE', 'permission')",
            deserialize: false,
        ),
        new Patch(security: "is_granted('EDIT', object)", deserialize: false),
        new Delete(security: "is_granted('DELETE', object)"),
    ],
    normalizationContext: ['groups' => [BasePermission::GROUP_READ]],
    denormalizationContext: ['groups' => [BasePermission::GROUP_EDIT]],
    paginationClientItemsPerPage: true,
    provider: 'gingerminds_core.api.provider.permission',
    processor: 'gingerminds_core.api.processor.permission',
)]
class Permission extends BasePermission
{
}
