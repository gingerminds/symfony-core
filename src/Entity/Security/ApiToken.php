<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Entity\Security;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Repository\Security\ApiTokenRepository;

#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
#[ORM\Table(name: 'api_tokens')]
#[ORM\Index(name: 'api_tokens_user_idx', columns: ['user_id'])]
class ApiToken
{
    public const string PREFIX = 'gm_';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastUsedAt = null;

    public function __construct(#[ORM\ManyToOne(targetEntity: UserInterface::class)]
        #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
        private UserInterface $user, #[ORM\Column(length: 255)]
        private string $name, string $plainToken, #[ORM\Column(nullable: true)]
        private ?DateTimeImmutable $expiresAt = null)
    {
        $this->tokenHash = self::hash($plainToken);
        $this->createdAt = new DateTimeImmutable();
    }

    public static function generatePlainToken(): string
    {
        return self::PREFIX . bin2hex(random_bytes(32));
    }

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function markUsed(DateTimeImmutable $at): void
    {
        $this->lastUsedAt = $at;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->expiresAt instanceof DateTimeImmutable && $this->expiresAt <= $now;
    }
}
