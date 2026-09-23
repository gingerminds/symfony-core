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

## Laravel components → form types

| Laravel `<x-form.inputs.*>` | Symfony |
|---|---|
| `basic` (`type=text\|number\|email\|date\|color`) | `TextType`, `NumberType`/`IntegerType`, `EmailType`, `DateType` (`widget: single_text`), `ColorType` — `attr: {min, max, step, placeholder}`, `help`, `required`, `disabled` |
| `textarea` | `TextareaType` (`attr: {rows: 10}`) |
| `select` (Select2) | `ChoiceType` / `EnumType` / `EntityType` with `'autocomplete' => true` (UX Autocomplete, Tom Select) |
| `select` with `ajax-url` | `EntityType` + `'autocomplete' => true` and UX Autocomplete's `#[AsEntityAutocompleteField]`, or `autocomplete_url` pointing at `/admin/_autocomplete/{resource}` |
| `toggle` | `CheckboxType` with `'label_attr' => ['class' => 'checkbox-switch']` — unchecked submits `false`, no hidden input needed |

Validation errors and old values are handled by the form component; nested/array fields are
embedded forms or `CollectionType`.

> There is no built-in WYSIWYG field, as in the Laravel core: add one at project level
> (e.g. a Stimulus-wrapped TipTap).

## Passwords

`RepeatedType` rows (password + confirmation) are rendered side by side. The API expects the
same structure: `"password": {"first": "...", "second": "..."}`.
