<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Entity\Role;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Entity\Permission\PermissionInterface;
use Gingerminds\CoreBundle\Entity\User\BaseUser;
use Gingerminds\CoreBundle\Model\SearchableInterface;
use Gingerminds\CoreBundle\Model\SortableInterface;
use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\CoreBundle\Model\Trait\TimestampableTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\MappedSuperclass]
#[UniqueEntity(fields: ['name'])]
abstract class BaseRole implements RoleInterface, TimestampableInterface, SortableInterface, SearchableInterface, \Stringable
{
    use TimestampableTrait;

    public const string GROUP_LIST = 'role:list';
    public const string GROUP_READ = 'role:read';
    public const string GROUP_EDIT = 'role:edit';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, BaseUser::GROUP_LIST, BaseUser::GROUP_READ])]
    protected ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_EDIT, BaseUser::GROUP_LIST, BaseUser::GROUP_READ])]
    protected ?string $name = null;

    #[ORM\Column(name: 'is_external', options: ['default' => false])]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_EDIT])]
    #[SerializedName('isExternal')]
    protected bool $external = false;

    #[ORM\Column(name: 'is_default', nullable: true)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_EDIT])]
    #[SerializedName('isDefault')]
    protected ?bool $default = null;

    /**
     * @var Collection<int, PermissionInterface>
     */
    #[ORM\ManyToMany(targetEntity: PermissionInterface::class)]
    #[ORM\JoinTable(name: 'role_permissions')]
    #[ORM\JoinColumn(name: 'role_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'permission_id', onDelete: 'CASCADE')]
    #[Groups([self::GROUP_READ])]
    protected Collection $permissions;

    public function __construct()
    {
        $this->permissions = new ArrayCollection();
    }

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

    public function isExternal(): bool
    {
        return $this->external;
    }

    public function setExternal(bool $external): void
    {
        $this->external = $external;
    }

    public function isDefault(): bool
    {
        return true === $this->default;
    }

    public function setDefault(bool $default): void
    {
        $this->default = $default ? true : null;
    }

    /**
     * @return Collection<int, PermissionInterface>
     */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addPermission(PermissionInterface $permission): void
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
        }
    }

    public function removePermission(PermissionInterface $permission): void
    {
        $this->permissions->removeElement($permission);
    }

    public function hasPermission(string $permission): bool
    {
        foreach ($this->permissions as $item) {
            if ($item->getName() === $permission) {
                return true;
            }
        }

        return false;
    }

    #[Groups([self::GROUP_LIST])]
    public function getPermissionsCount(): int
    {
        return $this->permissions->count();
    }

    public function getSecurityRole(): string
    {
        $slug = new AsciiSlugger()->slug((string) $this->name, '_')->upper()->toString();

        return 'ROLE_' . $slug;
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
