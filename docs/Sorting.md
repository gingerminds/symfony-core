# Sorting

Two unrelated features.

## 1. Column sorting (`#[Sortable]`)

`?order[property]=asc|desc` (API Platform `SortFilter`), on admin lists and API collections, only on the properties
marked `#[Sortable]`; on a to-one association, `paths` lists the sortable fields of the related
entity (the join is added automatically):

```php
use Gingerminds\CoreBundle\Model\Sortable;

#[ORM\Column]
#[Sortable]
private string $name = '';

#[ORM\ManyToOne]
#[Sortable(paths: ['name'])] // order[category.name]=desc
private ?Category $category = null;
```

Any other path is ignored (no parameter declares it), even a mapped one (`password`, `category.id`): a list never sorts
on a field nobody chose, nor joins at will. Every list also ends its ordering with the
identifier, so pagination stays stable. `SortableFields::of(Product::class)` returns the
allowed paths (parent classes included).

In `crud/list.html.twig`, a column whose `property` is a sortable path renders a sort link
(`gm_sort_path(route, property)`), toggling the direction on each click and keeping the filters; `sortable: false` opts a column out.

## 2. Drag & drop reordering (`crud/list_tree.html.twig`)

For hierarchical or manually ordered resources (categories, menus...): the server side is
`AbstractTreeCrudController` and `TreeRepositoryTrait`, the page a layout plus the
`gm-sortable-tree` Stimulus controller (Sortable.js).

### Entity

A `TreeNodeInterface`: a `parent` association of its own class (`children` on the other side
if you need it) and an integer `position` within its level. `#[NoTreeCycle]` refuses a parent
that is the node itself or one of its descendants (violation on `parent`):

```php
use Gingerminds\CoreBundle\Model\TreeNodeInterface;
use Gingerminds\CoreBundle\Validator\NoTreeCycle;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[NoTreeCycle]
class Category implements ResourceInterface, TreeNodeInterface
{
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Category $parent = null;

    #[ORM\Column]
    private int $position = 0;
    // getParent(), getPosition(), setPosition()
}
```

### Repository

```php
class CategoryRepository extends AbstractRepository implements TreeRepositoryInterface
{
    use TreeRepositoryTrait;
}
```

- `findTreeNodes()`: every node, from the list query (its context scope), ordered by level
  (`position`, then id);
- `reorderLevel($parentId, $ids)`: the order of one level, the ids of other levels ignored;
- `findIdsWithDescendants($ids)`: these nodes and their descendants (a filter on a branch);
- on save (`beforeSave()`), a new node, or one moved to another parent, goes to the end of its
  level.

Several trees in one table (the items of each menu): the methods take `$scope` criteria
(`['menu' => $menu]`), and `treeScope(TreeNodeInterface $node)` gives those of a node (end of its
level on save).

### Controller and templates

```php
#[AsCrudController(resource: 'category', entity: Category::class, form: CategoryType::class)]
final class CategoryController extends AbstractTreeCrudController
{
}
```

- the index renders the whole tree: `roots`, `children` (by parent id, `TreeNodes::ROOT` for
  the roots), `tree_reorder_url` (null without the EDIT permission: no drag & drop) and
  `tree_csrf_token_id`;
- `new?parent_id=5` preselects the parent of the new node;
- `reorder` (`/admin/categories/reorder`, route `<route_prefix>_reorder`) applies the order of a
  level: EDIT permission, CSRF token `reorder-<resource>` in the `X-CSRF-Token` header;
- `getTreeScope()` gives the `$scope` of the current route (the menu of a nested
  `menus/{menuId}/items` resource).

`TreeNodes::parentChoices($nodes, $node)` gives the possible parents of a node (not itself nor its
descendants) with their depth, for the parent field of the form.

```twig
{# templates/admin/category/index.html.twig #}
{% extends '@GingermindsCore/crud/list_tree.html.twig' %}

{% block tree %}
    {% include '@GingermindsCore/components/list/_tree.html.twig' with {nodes: roots} %}
{% endblock %}
```

`components/list/_tree.html.twig` renders each level: the drag handle, the label
(`__toString()`), an "add a child" link and the row actions. Your own partial follows the same
markup: each level is a `.sortable-level` (with `data-parent-id`, empty for the root) around
`.sortable-item` elements (with `data-item-id`) dragged by their `.drag-handle`. On drop, the
Stimulus controller POSTs the ordered ids of that level and its parent id:

```json
{"ids": [3, 1, 2], "parent_id": 5}
```

One level is reordered at a time; on error the page is reloaded. The
`gm-sortable-tree:reordered` event is dispatched after a successful save.
