<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Entity\User;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Entity\Role\RoleInterface;
use Gingerminds\CoreBundle\Model\SearchableInterface;
use Gingerminds\CoreBundle\Model\SortableInterface;
use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\CoreBundle\Model\Trait\TimestampableTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\MappedSuperclass]
#[UniqueEntity(fields: ['email'])]
abstract class BaseUser implements UserInterface, TimestampableInterface, SortableInterface, SearchableInterface, \Stringable
{
    use TimestampableTrait;

    public const string GROUP_LIST = 'user:list';
    public const string GROUP_READ = 'user:read';
    public const string GROUP_CREATE = 'user:create';
    public const string GROUP_EDIT = 'user:edit';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, BaseContributor::GROUP_LIST, BaseContributor::GROUP_READ])]
    protected ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    #[Groups([
        self::GROUP_LIST,
        self::GROUP_READ,
        self::GROUP_CREATE,
        self::GROUP_EDIT,
        BaseContributor::GROUP_LIST,
        BaseContributor::GROUP_READ,
    ])]
    protected ?string $email = null;

    #[ORM\Column(nullable: true)]
    protected ?string $password = null;

    /**
     * Never persisted: hashed into $password by UserRepository::beforeSave().
     */
    #[Assert\Length(min: 8, max: 4096)]
    protected ?string $plainPassword = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?\DateTimeImmutable $emailVerifiedAt = null;

    /**
     * @var Collection<int, RoleInterface>
     */
    #[ORM\ManyToMany(targetEntity: RoleInterface::class)]
    #[ORM\JoinTable(name: 'user_roles')]
    #[ORM\JoinColumn(name: 'user_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'role_id', onDelete: 'CASCADE')]
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    #[SerializedName('roles')]
    protected Collection $roleEntities;

    #[ORM\OneToOne(targetEntity: ContributorInterface::class, mappedBy: 'user')]
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    protected ?ContributorInterface $contributor = null;

    public function __construct()
    {
        $this->roleEntities = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getUserIdentifier(): string
    {
        if (null === $this->email || '' === $this->email) {
            throw new \LogicException('A user without email cannot be authenticated.');
        }

        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): void
    {
        $this->plainPassword = $plainPassword;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?\DateTimeImmutable $emailVerifiedAt): void
    {
        $this->emailVerifiedAt = $emailVerifiedAt;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        foreach ($this->roleEntities as $role) {
            $roles[] = $role->getSecurityRole();
        }

        return array_values(array_unique($roles));
    }

    /**
     * @return Collection<int, RoleInterface>
     */
    public function getRoleEntities(): Collection
    {
        return $this->roleEntities;
    }

    public function addRoleEntity(RoleInterface $role): void
    {
        if (!$this->roleEntities->contains($role)) {
            $this->roleEntities->add($role);
        }
    }

    public function removeRoleEntity(RoleInterface $role): void
    {
        $this->roleEntities->removeElement($role);
    }

    public function hasRole(string $roleName): bool
    {
        foreach ($this->roleEntities as $role) {
            if ($role->getName() === $roleName) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::SUPER_ADMIN_ROLE);
    }

    /**
     * @return list<string>
     */
    public function getPermissionNames(): array
    {
        $names = [];

        foreach ($this->roleEntities as $role) {
            foreach ($role->getPermissions() as $permission) {
                $names[] = (string) $permission->getName();
            }
        }

        return array_values(array_unique($names));
    }

    public function hasPermission(string $permission): bool
    {
        return \in_array($permission, $this->getPermissionNames(), true);
    }

    public function getContributor(): ?ContributorInterface
    {
        return $this->contributor;
    }

    public function setContributor(?ContributorInterface $contributor): void
    {
        $this->contributor = $contributor;
    }

    public static function getSearchableFields(): array
    {
        return ['email'];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0*\0password"] = null !== $this->password ? hash('crc32c', $this->password) : null;
        $data["\0*\0plainPassword"] = null;

        return $data;
    }

    public function __toString(): string
    {
        return (string) $this->email;
    }
}
