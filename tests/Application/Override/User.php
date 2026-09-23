<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\Override;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Entity\User\BaseUser;
use Gingerminds\CoreBundle\Repository\User\UserRepository;

/**
 * Project override of the bundle User (loaded in the `override` environment only).
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ApiResource(operations: [new Get(provider: 'gingerminds_core.api.provider.user')])]
class User extends BaseUser
{
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    public function getPhone(): ?string
    {
        return $this->phone;
    }
}
