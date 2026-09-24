<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Entity\User;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Repository\User\UserRepository;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ApiResource(
    shortName: 'User',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => [BaseUser::GROUP_LIST]],
            security: "is_granted('VIEW', 'user')",
        ),
        new Get(security: "is_granted('VIEW', object)"),
        new Post(
            denormalizationContext: ['groups' => [BaseUser::GROUP_CREATE]],
            security: "is_granted('CREATE', 'user')",
            deserialize: false,
        ),
        new Patch(security: "is_granted('EDIT', object)", deserialize: false),
        new Delete(security: "is_granted('DELETE', object)"),
    ],
    normalizationContext: ['groups' => [BaseUser::GROUP_READ]],
    denormalizationContext: ['groups' => [BaseUser::GROUP_EDIT]],
    paginationClientItemsPerPage: true,
    provider: 'gingerminds_core.api.provider.user',
    processor: 'gingerminds_core.api.processor.user',
)]
class User extends BaseUser
{
}
