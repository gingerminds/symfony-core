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
use Gingerminds\CoreBundle\Repository\User\ContributorRepository;

#[ORM\Entity(repositoryClass: ContributorRepository::class)]
#[ORM\Table(name: 'contributors')]
#[ApiResource(
    shortName: 'Contributor',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => [BaseContributor::GROUP_LIST]],
            security: "is_granted('VIEW', 'contributor')",
        ),
        new Get(security: "is_granted('VIEW', object)"),
        new Post(
            security: "is_granted('CREATE', 'contributor')",
            deserialize: false,
        ),
        new Patch(security: "is_granted('EDIT', object)", deserialize: false),
        new Delete(security: "is_granted('DELETE', object)"),
    ],
    normalizationContext: ['groups' => [BaseContributor::GROUP_READ]],
    denormalizationContext: ['groups' => [BaseContributor::GROUP_EDIT]],
    paginationClientItemsPerPage: true,
    provider: 'gingerminds_core.api.provider.contributor',
    processor: 'gingerminds_core.api.processor.contributor',
)]
class Contributor extends BaseContributor
{
}
