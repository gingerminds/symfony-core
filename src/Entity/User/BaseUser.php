<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Entity\User;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Entity\User\Trait\UserRolesTrait;
use Gingerminds\CoreBundle\Model\EagerLoad;
use Gingerminds\CoreBundle\Model\Searchable;
use Gingerminds\CoreBundle\Model\Sortable;
use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\CoreBundle\Model\Trait\TimestampableTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\MappedSuperclass]
#[UniqueEntity(fields: ['email'])]
abstract class BaseUser implements UserInterface, TimestampableInterface, \Stringable
{
    use TimestampableTrait;
    use UserRolesTrait;

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
    #[Sortable]
    #[Searchable]
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
     * Eager loaded: shown by the admin list and the `user:list` group, and the inverse side of a
     * one-to-one, which Doctrine can't lazy load (one query per user otherwise).
     */
    #[ORM\OneToOne(targetEntity: ContributorInterface::class, mappedBy: 'user')]
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    #[Sortable(paths: ['lastname'])]
    #[EagerLoad]
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

    public function getContributor(): ?ContributorInterface
    {
        return $this->contributor;
    }

    public function setContributor(?ContributorInterface $contributor): void
    {
        $this->contributor = $contributor;
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
