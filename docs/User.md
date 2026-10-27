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
(`contributorId = "__new__"` (`UserType::NEW_CONTRIBUTOR`) + `contributorFirstname`,
`contributorLastname`, ...), exactly like the Laravel core: `UserType::submitContributor()`, a
`POST_SUBMIT` listener of the admin form, applies them once the form is valid. The API writes the
`contributor` relation natively: an id or IRI links an existing contributor, a nested document
creates one (see [API](API.md#relations-by-id-or-by-iri)). Either way a new contributor is
persisted with the user (cascade persist), and `BaseUser::setContributor()` /
`BaseContributor::setUser()` keep both sides in sync. The contributor a user leaves is kept,
without user: `ContributorRepository::writeUserLinks()` (run by the user and contributor
`saveWith()`) releases it before inserting or linking the new one (`contributors.user_id` is
unique).

The plain password (`plainPassword` in the API, required on creation by the `user:create`
validation group, the `password` field of the form, `--password` of `gingerminds:create:user`) is hashed into `password` on flush by `UserPasswordListener`, a
Doctrine entity listener of the user entity (`preFlush`: it also runs when only the unmapped
plain password changed).

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

### Resource voter (Laravel policies)

One voter, `ResourceVoter`, applies the convention to every registered resource (core,
bundles, `#[AsCrudController]`, `gingerminds_core.resources`): no voter to write per resource.
Its attributes are `VIEW`, `CREATE`, `EDIT`, `DELETE`; the subject is an entity, its class or
the resource name, the entity class being the configured one (an overridden entity included):

```php
$this->denyAccessUnlessGranted('EDIT', $product);
```

```twig
{% if is_granted('CREATE', 'product') %}...{% endif %}
```

```php
#[ApiResource(operations: [new GetCollection(security: "is_granted('VIEW', 'product')")])]
```

The permission of each attribute is `ResourceDefinition::permissionFor()`: `view|edit|edit|delete
{permission}`, unless the resource maps another one (`roles` requires `manage roles` for
everything):

```yaml
gingerminds_core:
    resources:
        file:
            permissions: { DELETE: 'edit files' }   # deleting a file only needs `edit files`
```

```php
#[AsCrudController(resource: 'file', entity: File::class, permissions: [ResourceVoter::DELETE => 'edit files'])]
```

Built-in rules: a user always views and edits their own account and never deletes it; the
`Super-Admin` is granted everything (`SuperAdminVoter`). Anonymous users are denied.

**More access** (an author editing their own article, a resource readable without token): an
additional voter, autoconfigured in the project. The access decision strategy is `affirmative`
(Symfony's default): one voter granting is enough.

```php
/** @extends Voter<string, Article> */
final class ArticleAuthorVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return ResourceVoter::EDIT === $attribute && $subject instanceof Article;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $subject->getAuthor() === $token->getUser();
    }
}
```

**Less access** despite the permission (a voter cannot veto under `affirmative`): extend
`ResourceVoter`, override `voteOnResource()`, and set your class on its service
(`gingerminds_core.security.voter.resource`).

### Creating permissions

`bin/console gingerminds:permissions:sync` creates, idempotently:

- the core permissions (`access admin`, `view dashboard`, `view settings`, `manage roles`);
- the permissions of every registered resource (`view|edit|delete {permission}` or its
  `permissions` mapping);
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
