<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Entity\Permission;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Entity\Role\BaseRole;
use Gingerminds\CoreBundle\Model\SearchableInterface;
use Gingerminds\CoreBundle\Model\SortableInterface;
use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\CoreBundle\Model\Trait\TimestampableTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\MappedSuperclass]
#[UniqueEntity(fields: ['name'])]
abstract class BasePermission implements PermissionInterface, TimestampableInterface, SortableInterface, SearchableInterface, \Stringable
{
    use TimestampableTrait;

    public const string GROUP_LIST = 'permission:list';
    public const string GROUP_READ = 'permission:read';
    public const string GROUP_EDIT = 'permission:edit';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, BaseRole::GROUP_READ])]
    protected ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_EDIT, BaseRole::GROUP_READ])]
    protected ?string $name = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getGroup(): string
    {
        $parts = explode(' ', (string) $this->name, 2);

        return $parts[1] ?? 'other';
    }

    public static function getSearchableFields(): array
    {
        return ['name'];
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
