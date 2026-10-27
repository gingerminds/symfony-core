# Forms

Admin forms are plain Symfony form types rendered with
`@GingermindsCore/form/theme.html.twig` (extends `bootstrap_5_layout.html.twig`), applied
automatically by `crud/form.html.twig`. Elsewhere:

```twig
{% form_theme form '@GingermindsCore/form/theme.html.twig' %}
```

## The size system

The `size` option (added to every field by `SizeTypeExtension`) lays fields out on the
Bootstrap 12-column grid, like the Laravel components' `size` prop:

| `size` | Columns |
|---|---|
| `tiny` | 2 |
| `sm` | 4 |
| `md` | 6 (default for simple fields) |
| `lg` | 8 |
| `xl` | 12 |

```php
$builder
    ->add('name', TextType::class, ['label' => 'product.field.name', 'size' => 'lg'])
    ->add('code', TextType::class, ['label' => 'product.field.code', 'size' => 'sm']);
```

Compound widgets (the root form included) render as `.row.g-3` grids. When rendering rows one
by one, wrap them in `<div class="row g-3">`.

## Input groups

`input_prefix` / `input_suffix` (added to every field by `InputGroupTypeExtension`) show a text
before / after a single line input (`TextType`, `EmailType`, `UrlType`, `NumberType`...), in a
Bootstrap input group, like the Laravel components' `prefix` / `suffix` props. The texts are not
translated.

```php
$builder->add('slug', TextType::class, [
    'label' => 'page.field.slug',
    'input_prefix' => '/products/',
    'input_suffix' => '/',
]);
```

## Form layout and include slots

Lay a resource form out with the core two-column layout, embedded in its `_form.html.twig`
(the `make:gm:*` generators do):

```twig
{% trans_default_domain 'App' %}
{% embed '@GingermindsCore/crud/_form_layout.html.twig' %}
    {% trans_default_domain 'App' %}{# an embed has its own translation domain #}

    {% block main %}...cards of the main column...{% endblock %}
    {% block sidebar %}...cards of the right column (optional)...{% endblock %}
{% endembed %}
```

Each column ends with the templates of an admin include slot, so another bundle or the project
adds content to a form without overriding its template, typically the fields it adds to the
form type with a form type extension:

| Slot | Where |
|---|---|
| `<resource>_form_main` | end of the main column |
| `<resource>_form_sidebar` | end of the right column (a one-column form gets one) |

```yaml
gingerminds_core:
    admin_includes:
        user_form_sidebar: ['admin/user/_newsletter.html.twig']
```

```twig
{# templates/admin/user/_newsletter.html.twig: same variables as the form (form, entity, resource...) #}
{% if form.newsletter is defined %}
    <div class="card shadow-sm mb-4">
        <div class="card-body"><div class="row g-3">{{ form_row(form.newsletter, {size_columns: 12}) }}</div></div>
    </div>
{% endif %}
```

The resource name gives the slot names (`user_form_main`, `site_form_sidebar`...); the profile
page uses `profile_form_main` / `profile_form_sidebar`. Pass another name to the layout with
`{% embed '@GingermindsCore/crud/_form_layout.html.twig' with {form_includes_name: 'my_page'} %}`.
`gm_form_includes(name, 'main'|'sidebar')` returns the templates of a column.

## Laravel components → form types

| Laravel `<x-form.inputs.*>` | Symfony |
|---|---|
| `basic` (`type=text\|number\|email\|date\|color`) | `TextType`, `NumberType`/`IntegerType`, `EmailType`, `DateType` (`widget: single_text`), `ColorType` — `attr: {min, max, step, placeholder}`, `help`, `required`, `disabled`, `input_prefix` / `input_suffix` (`prefix` / `suffix`) |
| `textarea` | `TextareaType` (`attr: {rows: 10}`) |
| `select` (Select2) | `ChoiceType` / `EnumType` / `EntityType` with `'autocomplete' => true` (UX Autocomplete, Tom Select) |
| `select` with `ajax-url` | `EntityType` + `'autocomplete' => true` and UX Autocomplete's `#[AsEntityAutocompleteField]`, or `autocomplete_url` pointing at `/admin/_autocomplete/{resource}` |
| `toggle` | `CheckboxType` with `'label_attr' => ['class' => 'checkbox-switch']` — unchecked submits `false`, no hidden input needed |

Validation errors and old values are handled by the form component; nested/array fields are
embedded forms or `CollectionType`.

> There is no built-in WYSIWYG field, as in the Laravel core: add one at project level
> (e.g. a Stimulus-wrapped TipTap).

## Passwords

`RepeatedType` rows (password + confirmation) are rendered side by side. The confirmation is
admin only: the API takes a single `"plainPassword": "..."`.
