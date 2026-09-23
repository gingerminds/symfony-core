<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Entity\User;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Enum\Civility;
use Gingerminds\CoreBundle\Model\EagerLoadableInterface;
use Gingerminds\CoreBundle\Model\SearchableInterface;
use Gingerminds\CoreBundle\Model\SortableInterface;
use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\CoreBundle\Model\Trait\TimestampableTrait;
use Stringable;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

use function sprintf;

#[ORM\MappedSuperclass]
abstract class BaseContributor implements ContributorInterface, TimestampableInterface, SortableInterface, SearchableInterface, EagerLoadableInterface, Stringable
{
    use TimestampableTrait;

    public const string GROUP_LIST = 'contributor:list';
    public const string GROUP_READ = 'contributor:read';
    public const string GROUP_EDIT = 'contributor:edit';

    private const array PROFILE_GROUPS = [
        self::GROUP_LIST,
        self::GROUP_READ,
        self::GROUP_EDIT,
        BaseUser::GROUP_LIST,
        BaseUser::GROUP_READ,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, BaseUser::GROUP_LIST, BaseUser::GROUP_READ])]
    protected ?int $id = null;

    #[ORM\Column(length: 10, nullable: true, enumType: Civility::class)]
    #[Groups(self::PROFILE_GROUPS)]
    protected ?Civility $civility = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(self::PROFILE_GROUPS)]
    protected ?string $lastname = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(self::PROFILE_GROUPS)]
    protected ?string $firstname = null;

    #[ORM\Column(length: 3, nullable: true)]
    #[Assert\Length(max: 3)]
    #[Groups(self::PROFILE_GROUPS)]
    protected ?string $trigram = null;

    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $avatar = null;

    #[ORM\OneToOne(targetEntity: UserInterface::class, inversedBy: 'contributor')]
    #[ORM\JoinColumn(name: 'user_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_EDIT])]
    protected ?UserInterface $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): void
    {
        $this->firstname = $firstname;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): void
    {
        $this->lastname = $lastname;
    }

    public function getTrigram(): ?string
    {
        return $this->trigram;
    }

    public function setTrigram(?string $trigram): void
    {
        $this->trigram = $trigram;
    }

    public function getCivility(): ?Civility
    {
        return $this->civility;
    }

    public function setCivility(?Civility $civility): void
    {
        $this->civility = $civility;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): void
    {
        $this->avatar = $avatar;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): void
    {
        $this->user = $user;
        $user?->setContributor($this);
    }

    public function getFullName(): string
    {
        return trim(sprintf('%s %s', $this->firstname, $this->lastname));
    }

    public static function getEagerLoads(): array
    {
        return ['user'];
    }

    public static function getSearchableFields(): array
    {
        return ['firstname', 'lastname', 'trigram'];
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}
