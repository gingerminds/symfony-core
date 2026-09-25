# Layouts

Every admin page extends one of these Twig layouts (namespace `@GingermindsCore`).
Each layout documents its full variable/block API in its header comment.

```
layout/_document.html.twig        shared HTML document: <head> (meta, title, importmap, admin.scss), <body>
 ├── layout/guest.html.twig       guest pages (login)
 └── layout/base.html.twig        authenticated shell: sidebar (menu + user menu), flashes, breadcrumb
      ├── crud/list.html.twig          paginated list with search/filters/sort
      ├── crud/list_tree.html.twig     hierarchical list with drag & drop
      ├── crud/form.html.twig          create/edit form
      │    └── crud/form_tabs.html.twig  tabbed form
      └── crud/show.html.twig          bare content wrapper for custom pages
```

**Configuration variables** are set with a top-level `{% set %}` in the child template
(outside any block): Twig runs them before rendering the parent.

Override any bundle template the Symfony way:
`templates/bundles/GingermindsCoreBundle/<path>.html.twig`.

## `layout/base.html.twig`

Variables: `page_title`, `breadcrumb` (list of `{label, url?}`; the dashboard is prepended).

Blocks: `title`, `stylesheets`, `javascripts` (override the whole block to add entrypoints —
`importmap()` can only be called once per page), `body_attributes`, `sidebar`,
`footer`, `page_header`, `page_title`, `breadcrumb`, `page_actions`, `flashes`, `content`,
`modals`. Inside the sidebar (`layout/_sidebar.html.twig`): `sidebar_logo` (dashboard link
with the admin title, override it to put the project logo) and `sidebar_profile` (current
user menu: profile, sign out, pinned at the bottom).

The sidebar comes from `gm_admin_menu()`: add entries by implementing
`Gingerminds\CoreBundle\Menu\AdminMenuProviderInterface` (autoconfigured, the Laravel
`dashboard_menu_items` partial):

```php
final class CatalogMenuProvider implements AdminMenuProviderInterface
{
    public function getItems(): iterable
    {
        yield new MenuItem('menu.catalog', icon: 'bi-box', priority: 100, translationDomain: 'admin', children: [
            new MenuItem('product.name_p', 'admin_product_index', icon: 'bi-tag', permission: 'VIEW', permissionSubject: 'product', translationDomain: 'admin'),
        ]);
    }
}
```

Entries are sorted by `priority` (highest first), hidden when `permission` is not granted,
and empty sections are dropped. The active state matches the route and its CRUD siblings
(`admin_product_*`) exactly, avoiding the Laravel prefix-collision issue.

## `crud/list.html.twig`

Receives `resource`, `items` (Paginator), `list_query`, `filters`, `filter_configs`,
`is_searchable`, `is_filterable`, `is_sortable` from `AbstractCrudController::index()`.
Renders the collapsible filters panel (when the entity is searchable/filterable), the card
with the table, the items-per-page selector and the pagination.

```twig
{% extends '@GingermindsCore/crud/list.html.twig' %}

{% set columns = [
    {label: '#', align: 'center'},
    {label: 'product.field.name', property: 'name', sortable: true},
    {label: 'product.field.category', property: 'category.name', sortable: true},
    {label: 'common.actions', align: 'end'},
] %}

{% block table_row %}
    <td class="text-center">{{ row_number }}</td>
    <td>{{ item.name }}</td>
    <td>{{ item.category }}</td>
    <td class="text-end">{{ block('row_actions') }}</td>
{% endblock %}
```

- `columns`: `{label, property?, sortable?, align?, class?, translation_domain?}` — labels are
  translation keys (resource domain, then `GingermindsCore`); a column with `property` and
  `sortable` gets a sort link when the entity implements `SortableInterface`.
- Other variables: `page_title`, `list_title`, `breadcrumb`, `route`/`route_parameters`
  (nested resources), `show_pagination`, `items_per_page_options`.
- Blocks: `list_before`, `list_after`, `list_filters`, `list_icon`, `list_title`,
  `list_actions` (create button, `is_granted('CREATE', resource.name)`), `table`,
  `table_head`, `table_body`, `table_row` (`item`, `row_number`), `table_row_attributes`,
  `table_empty`, `table_empty_icon`, `row_actions` (edit/delete buttons, guarded by
  `is_granted('EDIT'|'DELETE', item)`), `list_footer`.
- The delete confirmation modal is included automatically (`gm-delete-modal`); deletion is
  a POST with a `delete-{id}` CSRF token.

## `crud/list_tree.html.twig`

Same chrome as `crud/list`, with a `tree` block and drag & drop (Stimulus
`gm-sortable-tree` + Sortable.js). See [Sorting](../Sorting.md#2-drag--drop-reordering-crudlist_treehtmltwig).

## `crud/form.html.twig`

Receives `resource`, `entity`, `form`, `is_new`. Applies the admin
[form theme](forms.md), renders the fields, and the cancel/save buttons.

```twig
{% extends '@GingermindsCore/crud/form.html.twig' %}

{% block form_body %}
    {{ include('admin/product/_form.html.twig') }}
{% endblock %}
```

Variables: `page_title` (default `<resource>.title_new|title_edit`), `breadcrumb`,
`index_url` (cancel target, `false` hides it), `form_id`, `form_themes`, `form_attr`,
`is_disabled`. Blocks: `form_subheader`, `form_nav`, `form_body` (default: every field in a
card), `form_buttons`, `additional_infos`. Works without a resource too (profile page).

## `crud/form_tabs.html.twig`

`crud/form` with `tabs` (headers) and `tab_content` (panes) blocks; unrendered fields are
output after the tabs.

## `crud/show.html.twig`

The authenticated shell with a `sub_content` block, for custom detail pages.

## Components & macros

`{% import '@GingermindsCore/components/macros.html.twig' as gm %}`: `trans` (with domain
fallback), `sort_header`, `boolean_badge`, `create_button`, `edit_button`, `delete_button`, `row_actions`, `confirm_link`.
Partials: `components/_flashes`, `components/_breadcrumb`, `components/display/_boolean`,
`components/list/{_filters,_pagination,_items_per_page}`,
`components/list/filter/_{text,date,number,boolean,select,select_entity}`,
`components/modal/{_modal,_confirm,_delete}`.

A filter config may set `template` to render a custom filter type.

## Stimulus controllers

Registered by `gingerminds-core/admin.js`:

| Controller | Purpose |
|---|---|
| `gm-sidebar` | collapse the sidebar (remembered in localStorage) |
| `gm-delete-modal` | fills the delete modal from the clicked button (`data-gm-delete-url`, `data-gm-delete-token`, `data-gm-delete-label`) |
| `gm-confirm-modal` | generic confirmation (`data-gm-confirm-url`) |
| `gm-sortable-tree` | drag & drop reordering |
| `gm-check-all` | "check all" per group (role permissions) |
| `gm-items-per-page` | items-per-page selector |
| `gm-contributor-fields` | shows the contributor fields of the user form when needed |
| `gm-password-toggle` | show/hide a password |

Autocomplete fields use UX Autocomplete's `symfony--ux-autocomplete--autocomplete`.
