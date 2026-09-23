# Sorting

Two unrelated features.

## 1. Column sorting (`SortableInterface`)

Marker interface enabling `?sortBy=property&sort=asc|desc`, on admin lists and API
collections:

```php
class Product implements ResourceInterface, SortableInterface {}
```

`sortBy` accepts a mapped field or `relation.field` through a to-one association
(`category.name`); the join is added automatically. Anything else is ignored: the path is
checked against the Doctrine metadata, never injected into DQL. Every list also ends its
ordering with the identifier, so pagination stays stable.

In `crud/list.html.twig`, a column declared with `property` and `sortable: true` renders a
sort link (`gm_sort_path()`), toggling the direction on each click.

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

The endpoint, in your `AbstractCrudController` subclass (its `#[Route]` attributes are loaded
by your usual `config/routes.yaml` controllers import):

```php
#[Route('/admin/categories/reorder', name: 'admin_category_reorder', methods: ['POST'])]
public function reorder(Request $request): JsonResponse
{
    $this->denyAccessUnlessGranted('EDIT', 'category');

    if (!$this->isCsrfTokenValid('reorder-category', $request->headers->get('X-CSRF-Token'))) {
        throw new AccessDeniedHttpException('Invalid CSRF token.');
    }

    $repository = $this->getRepository();

    foreach ($request->getPayload()->all('ids') as $position => $id) {
        $repository->findOneForRead($id)?->setPosition($position);
    }

    $this->context->doctrine->getManager()->flush();

    return new JsonResponse(['success' => true]);
}
```

One level is reordered at a time; on error the page is reloaded. The
`gm-sortable-tree:reordered` event is dispatched after a successful save.
