<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Entity\Role;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Repository\Role\RoleRepository;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: 'roles')]
#[ORM\UniqueConstraint(name: 'roles_default_unique', columns: ['is_external', 'is_default'])]
#[ApiResource(
    shortName: 'Role',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => [BaseRole::GROUP_LIST]],
            security: "is_granted('VIEW', 'role')",
        ),
        new Get(security: "is_granted('VIEW', object)"),
        new Post(security: "is_granted('CREATE', 'role')"),
        new Patch(security: "is_granted('EDIT', object)"),
        new Delete(
            security: "is_granted('DELETE', object)",
            validationContext: ['groups' => [BaseRole::GROUP_DELETE]],
            validate: true,
        ),
    ],
    normalizationContext: ['groups' => [BaseRole::GROUP_READ]],
    denormalizationContext: ['groups' => [BaseRole::GROUP_EDIT]],
    paginationClientItemsPerPage: true,
)]
class Role extends BaseRole
{
}
