<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\Override;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Entity\User\BaseUser;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Project override of the bundle User (loaded in the `override` environment only): the
 * bundle #[ApiResource] restated, as a project does to keep the API of the resource.
 */
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
            validationContext: ['groups' => ['Default', BaseUser::GROUP_CREATE]],
        ),
        new Patch(security: "is_granted('EDIT', object)"),
        new Delete(security: "is_granted('DELETE', object)"),
    ],
    normalizationContext: ['groups' => [BaseUser::GROUP_READ]],
    denormalizationContext: ['groups' => [BaseUser::GROUP_EDIT]],
    paginationClientItemsPerPage: true,
)]
class User extends BaseUser
{
    #[ORM\Column(length: 30, nullable: true)]
    #[Groups([BaseUser::GROUP_READ, BaseUser::GROUP_CREATE, BaseUser::GROUP_EDIT])]
    private ?string $phone = null;

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }
}
