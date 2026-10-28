<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

<?= $use_statements ?>

#[ORM\Entity(repositoryClass: <?= $repository_class ?>::class)]
#[ORM\Table(name: '<?= $resource->snakePlural ?>')]
// Index the default sort and the sortable / filtered columns once the table can grow:
// #[ORM\Index(name: '<?= $resource->snakePlural ?>_name_idx', fields: ['name'])]
<?php if ($api): include $skeleton_directory . '/ApiResource.tpl.php'; endif ?>
class <?= $class_name ?> implements <?= implode(', ', ['ResourceInterface', 'TimestampableInterface', ...$interfaces, '\Stringable']) ?>

{
    use TimestampableTrait;
<?php foreach ($traits as $trait): ?>
    use <?= $trait ?>;
<?php endforeach ?>
<?php if ($api): ?>

    public const string GROUP_LIST = '<?= $resource->snake ?>:list';
    public const string GROUP_READ = '<?= $resource->snake ?>:read';
    public const string GROUP_EDIT = '<?= $resource->snake ?>:edit';
<?php endif ?>

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Sortable]
<?php if ($api): ?>
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
<?php endif ?>
    private ?int $id = null;

    // TODO: mark the list parameters of the API collection and the admin list on the properties:
    // #[Sortable] (`order[name]=asc|desc`), #[Searchable] (`search=`; #[Searchable(paths: ['name'])]
    // on an association searches `relation.name`), #[Filter] (`status=draft`, `price[gte]=10`,
    // `publishedAt[after]=2024-01-01`, `category=5`; label/choices for the admin panel), and the
    // relations shown by the admin list or the API list group with #[EagerLoad] (no query per row).

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
