<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\Override;

use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Entity\User\BaseUser;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Project override of the bundle User (loaded in the `override` environment only): no
 * #[ApiResource], the bundle one applies (OverriddenEntityResourceMetadataCollectionFactory).
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
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
