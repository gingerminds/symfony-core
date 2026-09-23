<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Model\ResourceInterface;
use Gingerminds\CoreBundle\Model\SearchableInterface;
use Gingerminds\CoreBundle\Tests\Application\Repository\CategoryRepository;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'categories')]
class Category implements ResourceInterface, SearchableInterface, \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:list'])]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        #[Groups(['product:list'])]
        private string $name = '',
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public static function getSearchableFields(): array
    {
        return ['name'];
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
