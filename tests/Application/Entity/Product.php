<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\CoreBundle\Model\CacheableResourceInterface;
use Gingerminds\CoreBundle\Model\EagerLoad;
use Gingerminds\CoreBundle\Model\Filter;
use Gingerminds\CoreBundle\Model\ResourceInterface;
use Gingerminds\CoreBundle\Model\Searchable;
use Gingerminds\CoreBundle\Model\Sortable;
use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\CoreBundle\Model\Trait\CacheableResourceTrait;
use Gingerminds\CoreBundle\Model\Trait\TimestampableTrait;
use Gingerminds\CoreBundle\Tests\Application\Enum\ProductStatus;
use Gingerminds\CoreBundle\Tests\Application\Repository\ProductRepository;
use Gingerminds\CoreBundle\Tests\Application\State\ProductProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Test resource exercising every list feature: all filter types, search
 * through a relation, sorting, eager loads, facets and the API cache.
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
#[ApiResource(
    operations: [
        new GetCollection(normalizationContext: ['groups' => ['product:list']], provider: ProductProvider::class),
        new Get(normalizationContext: ['groups' => ['product:list']], extraProperties: ['rate_limiter' => 'test_strict']),
    ],
    paginationClientItemsPerPage: true,
)]
class Product implements ResourceInterface, CacheableResourceInterface, TimestampableInterface, \Stringable
{
    use CacheableResourceTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:list'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['product:list'])]
    #[Sortable]
    #[Searchable]
    #[Filter(label: 'product.field.name', admin: false)]
    private string $name = '';

    #[ORM\Column(type: Types::FLOAT)]
    #[Groups(['product:list'])]
    #[Filter(label: 'product.field.price')]
    private float $price = 0;

    #[ORM\Column(nullable: true)]
    #[Groups(['product:list'])]
    #[Filter(label: 'product.field.active')]
    private ?bool $active = null;

    #[ORM\Column(length: 20, enumType: ProductStatus::class)]
    #[Groups(['product:list'])]
    #[Filter(
        label: 'product.field.status',
        choices: ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'],
        multiple: true,
    )]
    private ProductStatus $status = ProductStatus::Draft;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['product:list'])]
    #[Filter(label: 'product.field.published_at')]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['product:list'])]
    #[Sortable(paths: ['name'])]
    #[Searchable(paths: ['name'])]
    #[EagerLoad]
    #[Filter(label: 'product.field.category', resource: 'category')]
    private ?Category $category = null;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class)]
    #[ORM\JoinTable(name: 'product_tags')]
    #[Filter(label: 'product.field.tags', multiple: true)]
    private Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public static function getCacheKey(): string
    {
        return 'product';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getStatus(): ProductStatus
    {
        return $this->status;
    }

    public function setStatus(ProductStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Category $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Category $tag): void
    {
        $this->tags->removeElement($tag);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
