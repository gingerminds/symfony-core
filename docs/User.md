# Users, roles & permissions

## The User / Contributor split

Same model as the Laravel core:

- **User** (`UserInterface`, table `users`) authenticates: email, password, roles.
- **Contributor** (`ContributorInterface`, table `contributors`) is the person's profile:
  civility, first/last name, trigram, avatar. It references the user (`user_id`, nullable,
  `ON DELETE SET NULL`), so profile data survives the deletion of a
  login account.

Any feature needing "who is this" data should reference the contributor, not the user.

The user form links the account to an existing contributor or creates one
(`contributorId = "__new__"` + `contributorFirstname`, `contributorLastname`, ...), exactly
like the Laravel core, through `UserRepository::beforeSave()`.

## Roles & permissions

Roles and permissions are **entities administrable from the admin** (`/admin/roles`,
`/admin/permissions`), replacing `spatie/laravel-permission`:

- a user has many roles (`user_roles`), a role has many permissions (`role_permissions`);
- a role can be `external` (front-office users) and `default` (at most one default role per
  audience, enforced on save and by a unique index);
- the `Super-Admin` role is granted everything (`SuperAdminVoter`, the Laravel `Gate::before`)
  and cannot be deleted.

Symfony security roles are derived from role names (`Super-Admin` → `ROLE_SUPER_ADMIN`,
`Admin` → `ROLE_ADMIN`) plus `ROLE_USER`, but fine-grained checks should use permissions.

### Permission convention

`{action} {resource}`, lowercase:

1. `view products` — list and detail pages, API reads;
2. `edit products` — create and edit;
3. `delete products` — deletion.

Check a permission anywhere:

```php
$this->denyAccessUnlessGranted('view dashboard');   // PermissionNameVoter
#[IsGranted('edit settings')]
```

```twig
{% if is_granted('manage roles') %}...{% endif %}
```

### Resource voters (Laravel policies)

`AbstractResourceVoter` implements the convention once, for the attributes `VIEW`, `CREATE`,
`EDIT`, `DELETE`; the subject is an entity, its class or the resource name:

```php
$this->denyAccessUnlessGranted('EDIT', $product);
```

```twig
{% if is_granted('CREATE', 'product') %}...{% endif %}
```

```php
#[ApiResource(operations: [new GetCollection(security: "is_granted('VIEW', 'product')")])]
```

Override `canView()`, `canCreate()`, `canEdit()`, `canDelete()` for specific rules — e.g. the
bundle `UserVoter` lets a user view/edit their own account and forbids deleting it,
`RoleVoter` requires `manage roles` for everything. A resource readable by anyone:

```php
protected function canView(UserInterface $user, ?object $subject): bool
{
    return true;
}
```

### Creating permissions

`bin/console gingerminds:permissions:sync` creates, idempotently:

- the core permissions (`access admin`, `view dashboard`, `view settings`, `manage roles`);
- `view|edit|delete {permission}` for every registered resource;
- the `gingerminds_core.permissions` list;
- the `Super-Admin` and `Admin` roles.

Permissions can also be created from the admin (`/admin/permissions`).

## Commands

```bash
bin/console gingerminds:create:user
bin/console gingerminds:create:user --email=admin@example.com --role=Super-Admin --lastname=Doe --firstname=John
```

Creates the user and its contributor in one transaction and assigns the role (asked
interactively when not given). Requires existing roles (run `gingerminds:permissions:sync` first).
