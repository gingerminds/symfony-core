<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

<?= $use_statements ?>

#[ORM\Entity(repositoryClass: <?= $repository_class ?>::class)]
#[ORM\Table(name: '<?= $resource->snakePlural ?>')]
<?php if ($api): include __DIR__ . '/ApiResource.tpl.php'; endif ?>
class <?= $class_name ?> implements ResourceInterface, SearchableInterface, SortableInterface, TimestampableInterface, \Stringable
{
    use TimestampableTrait;
<?php if ($api): ?>

    public const string GROUP_LIST = '<?= $resource->snake ?>:list';
    public const string GROUP_READ = '<?= $resource->snake ?>:read';
    public const string GROUP_EDIT = '<?= $resource->snake ?>:edit';
<?php endif ?>

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
<?php if ($api): ?>
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
<?php endif ?>
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
<?php if ($api): ?>
    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_EDIT])]
<?php endif ?>
    private ?string $name = null;

    public function __toString(): string
    {
        return (string) $this->name;
    }

    public static function getSearchableFields(): array
    {
        // TODO: list the fields matched by `filters[search]` (`relation.field` supported).
        return ['name'];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
