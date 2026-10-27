# Sorting

Two unrelated features.

## 1. Column sorting (`#[Sortable]`)

`?sortBy=property&sort=asc|desc`, on admin lists and API collections, only on the properties
marked `#[Sortable]`; on a to-one association, `paths` lists the sortable fields of the related
entity (the join is added automatically):

```php
use Gingerminds\CoreBundle\Model\Sortable;

#[ORM\Column]
#[Sortable]
private string $name = '';

#[ORM\ManyToOne]
#[Sortable(paths: ['name'])] // sortBy=category.name
private ?Category $category = null;
```

Any other path is ignored, even a mapped one (`password`, `category.id`): a list never sorts
on a field nobody chose, nor joins at will. Every list also ends its ordering with the
identifier, so pagination stays stable. `SortableFields::of(Product::class)` returns the
allowed paths (parent classes included).

In `crud/list.html.twig`, a column whose `property` is a sortable path renders a sort link
(`gm_sort_path()`), toggling the direction on each click; `sortable: false` opts a column out.

## 2. Drag & drop reordering (`crud/list_tree.html.twig`)

For hierarchical or manually ordered resources (categories, menus...): a layout plus the
`gm-sortable-tree` Stimulus controller (Sortable.js).

```twig
{% extends '@GingermindsCore/crud/list_tree.html.twig' %}

{% set tree_reorder_url = path('admin_category_reorder') %}
{% set tree_csrf_token_id = 'reorder-category' %}

{% block tree %}
    {% include 'admin/category/_tree.html.twig' with {tree_items: roots, depth: 0} %}
{% endblock %}
```

Each level is a `.sortable-level` (with `data-parent-id`, empty for the root) around
`.sortable-item` elements (with `data-item-id`) dragged by their `.drag-handle`:

```twig
<div class="sortable-level" data-parent-id="{{ parent_id ?? '' }}">
    {% for item in tree_items %}
        <div class="sortable-item" data-item-id="{{ item.id }}">
            <div class="tree-item-row d-flex align-items-center p-2">
                <i class="bi bi-grip-vertical drag-handle me-2"></i> {{ item.name }}
            </div>
            {% if item.children is not empty %}
                {% include 'admin/category/_tree.html.twig' with {tree_items: item.children, parent_id: item.id, depth: depth + 1} %}
            {% endif %}
        </div>
    {% endfor %}
</div>
```

On drop, the controller POSTs JSON — the ordered ids of that level and its parent id — with
the CSRF token in the `X-CSRF-Token` header:

```json
{"ids": [3, 1, 2], "parent_id": 5}
```

The endpoint, in your `AbstractCrudController` subclass: like the CRUD actions, its `#[Route]`
is relative to the resource (`/admin/categories/reorder`, route `admin_category_reorder`, the
`_resource` default of `getRepository()` set by the core route loader):

```php
#[Route('/reorder', name: 'reorder', methods: ['POST'])]
#[IsCsrfTokenValid('reorder-category', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
public function reorder(Request $request): JsonResponse
{
    $this->denyAccessUnlessGranted('EDIT', 'category');

    $repository = $this->getRepository();

    foreach ($request->getPayload()->all('ids') as $position => $id) {
        $repository->findOneForRead($id)?->setPosition($position);
    }

    $this->getDoctrine()->getManager()->flush();

    return $this->json(['success' => true]);
}
```

One level is reordered at a time; on error the page is reloaded. The
`gm-sortable-tree:reordered` event is dispatched after a successful save.
