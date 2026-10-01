<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

<?= $use_statements ?>

#[ORM\Entity(repositoryClass: <?= $repository_class ?>::class)]
#[ORM\Table(name: '<?= $resource->snakePlural ?>')]
// Index the default sort and the sortable / filtered columns once the table can grow:
// #[ORM\Index(name: '<?= $resource->snakePlural ?>_name_idx', fields: ['name'])]
<?php if ($api): include $skeleton_directory . '/ApiResource.tpl.php'; endif ?>
class <?= $class_name ?> implements <?= implode(', ', ['ResourceInterface', 'SearchableInterface', 'SortableInterface', 'TimestampableInterface', ...$interfaces, ...([] !== $eager_loads ? ['EagerLoadableInterface'] : []), '\Stringable']) ?>

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
<?php if ($api): ?>
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
<?php endif ?>
    private ?int $id = null;

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public static function getSearchableFields(): array
    {
        // TODO: list the fields matched by `filters[search]` (`relation.field` supported).
        return [];
    }

<?php if ([] !== $eager_loads): ?>
    /**
     * Relations shown by the admin list or the API list group (no query per row).
     */
    public static function getEagerLoads(): array
    {
        return [<?= implode(', ', $eager_loads) ?>];
    }

<?php else: ?>
    // Relations shown by the admin list or the API list group: implement EagerLoadableInterface
    // and return them from getEagerLoads(), e.g. ['category', 'tags'] (no query per row).

<?php endif ?>
    public function getId(): ?int
    {
        return $this->id;
    }
}
