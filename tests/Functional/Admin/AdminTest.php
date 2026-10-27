<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Entity\Permission\Permission;
use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Entity\User\User;
use Gingerminds\CoreBundle\Form\User\UserType;
use Gingerminds\CoreBundle\Tests\Application\Entity\Category;
use Gingerminds\CoreBundle\Tests\Application\Entity\Product;
use Gingerminds\CoreBundle\Tests\Functional\ApiTestCase;
use Gingerminds\CoreBundle\Tests\Functional\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminTest extends ApiTestCase
{
    public function testAnonymousUsersAreRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin/users');

        self::assertResponseRedirects('/admin/login');
    }

    public function testFormLogin(): void
    {
        $this->fixtures->user('login@example.com', superAdmin: true);

        $crawler = $this->client->request('GET', '/admin/login');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form([
            '_username' => 'login@example.com',
            '_password' => Fixtures::PASSWORD,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testFormLoginRejectsBadCredentials(): void
    {
        $this->fixtures->user('bad@example.com');

        $crawler = $this->client->request('GET', '/admin/login');
        $this->client->submit($crawler->filter('form')->form(['_username' => 'bad@example.com', '_password' => 'nope']));

        self::assertResponseRedirects('/admin/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pages(): iterable
    {
        foreach (['/admin/', '/admin/profile', '/admin/users', '/admin/users/new', '/admin/contributors', '/admin/contributors/new', '/admin/roles', '/admin/roles/new', '/admin/permissions', '/admin/permissions/new'] as $uri) {
            yield $uri => [$uri];
        }

        yield 'list with query' => ['/admin/users?filters[search]=example&sortBy=email&sort=desc&itemsPerPage=5&page=1'];
    }

    #[DataProvider('pages')]
    public function testPagesRender(string $uri): void
    {
        $this->client->loginUser($this->fixtures->user('pages@example.com', superAdmin: true), 'admin');

        $this->client->request('GET', $uri);

        self::assertResponseIsSuccessful();
    }

    public function testSearchFieldOfASearchableResource(): void
    {
        $this->client->loginUser($this->fixtures->user('search@example.com', superAdmin: true), 'admin');

        $emails = fn (string $search): array => $this->client->request('GET', '/admin/users?filters[search]=' . $search)
            ->filter('tbody tr td:nth-child(2)')->each(static fn ($td): string => trim($td->text()));

        self::assertSame(['search@example.com'], $emails('SEARCH@'));
        self::assertCount(1, $this->client->getCrawler()->filter('input[name="filters[search]"]'));
        // `email` is `#[Searchable]`, not the contributor firstname of the user (`First`).
        self::assertSame([], $emails('first'));
    }

    public function testOnlySortableColumnsLinkToASort(): void
    {
        $this->client->loginUser($this->fixtures->user('sort@example.com', superAdmin: true), 'admin');

        $crawler = $this->client->request('GET', '/admin/users');

        self::assertSame(['email', 'contributor.lastname'], $crawler->filter('th.sortable')->each(static fn ($th): ?string => $th->attr('data-sort')));
        self::assertStringContainsString('sortBy=email', (string) $crawler->filter('th[data-sort="email"] a')->attr('href'));
    }

    public function testListHeadHasPaginationLinks(): void
    {
        $this->client->loginUser($this->fixtures->user('rel-a@example.com', superAdmin: true), 'admin');
        $this->fixtures->user('rel-b@example.com');
        $this->fixtures->user('rel-c@example.com');

        $crawler = $this->client->request('GET', '/admin/users?itemsPerPage=1');
        self::assertCount(0, $crawler->filter('head link[rel="prev"]'));
        self::assertSame('http://localhost/admin/users?itemsPerPage=1&page=2', $crawler->filter('head link[rel="next"]')->attr('href'));

        $crawler = $this->client->request('GET', '/admin/users?itemsPerPage=1&page=2');
        self::assertSame('http://localhost/admin/users?itemsPerPage=1', $crawler->filter('head link[rel="prev"]')->attr('href'));
        self::assertSame('http://localhost/admin/users?page=3&itemsPerPage=1', $crawler->filter('head link[rel="next"]')->attr('href'));

        $crawler = $this->client->request('GET', '/admin/users?itemsPerPage=1&page=3');
        self::assertSame('http://localhost/admin/users?page=2&itemsPerPage=1', $crawler->filter('head link[rel="prev"]')->attr('href'));
        self::assertCount(0, $crawler->filter('head link[rel="next"]'));
    }

    public function testUserListQueryCountDoesNotDependOnTheRows(): void
    {
        // One kernel for the whole test: the query collector below is the one of the requests.
        $this->client->disableReboot();
        $this->client->loginUser($this->fixtures->user('rows-0@example.com', superAdmin: true), 'admin');
        $this->fixtures->user('rows-1@example.com', ['view rows']);
        $this->fixtures->user('rows-2@example.com', ['view rows']);
        $few = $this->listQueryCount('/admin/users');

        foreach (range(3, 7) as $i) {
            $this->fixtures->user('rows-' . $i . '@example.com', ['view rows']);
        }

        // Contributors (inverse one-to-one) and roles are eager loaded, not one query per user.
        self::assertSame($few, $this->listQueryCount('/admin/users'));
    }

    public function testRoleListQueryCountDoesNotDependOnTheRows(): void
    {
        $this->client->disableReboot();
        $this->client->loginUser($this->fixtures->user('roles@example.com', superAdmin: true), 'admin');
        $this->fixtures->role('Role 1', ['view one', 'view two']);
        $few = $this->listQueryCount('/admin/roles');

        foreach (range(2, 6) as $i) {
            $this->fixtures->role('Role ' . $i, ['view one', 'view two']);
        }

        // Permissions counted by the list are loaded for the page, not once per role.
        self::assertSame($few, $this->listQueryCount('/admin/roles'));
    }

    public function testEditPagesRender(): void
    {
        $user = $this->fixtures->user('edit@example.com', superAdmin: true);
        $this->client->loginUser($user, 'admin');
        $role = $this->fixtures->role('Editable', ['view editables']);

        foreach ([
            '/admin/users/' . $user->getId() . '/edit',
            '/admin/contributors/' . $user->getContributor()?->getId() . '/edit',
            '/admin/roles/' . $role->getId() . '/edit',
            '/admin/permissions/' . $role->getPermissions()->first()->getId() . '/edit',
        ] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseIsSuccessful($uri);
        }
    }

    public function testMenuOnlyShowsGrantedEntries(): void
    {
        $this->client->loginUser($this->fixtures->user('menu@example.com', ['view users']), 'admin');

        $crawler = $this->client->request('GET', '/admin/');

        self::assertCount(1, $crawler->filter('a[href="/admin/users"]'));
        self::assertCount(0, $crawler->filter('a[href="/admin/roles"]'));
    }

    public function testNestedResourceKeepsItsParentInTheLinksAndRedirects(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $category = new Category('Tractors');
        $entityManager->persist($category);
        $entityManager->flush();
        $base = '/admin/categories/' . $category->getId() . '/products';
        $this->client->loginUser($this->fixtures->user('nested@example.com', superAdmin: true), 'admin');

        $crawler = $this->client->request('GET', $base . '/new');
        self::assertSame($base, $crawler->filter('.crud-form a.btn-secondary')->attr('href'), 'Cancel link');
        $this->client->submit($crawler->filter('form[name="category_product"]')->form(['category_product[name]' => 'T-800']));
        self::assertResponseRedirects($base);

        $product = $entityManager->getRepository(Product::class)->findOneBy(['name' => 'T-800']);
        self::assertInstanceOf(Product::class, $product);
        self::assertSame($category->getId(), $product->getCategory()?->getId());

        // Global redirect after edit: the list.
        $crawler = $this->client->request('GET', $base . '/' . $product->getId() . '/edit');
        $this->client->submit($crawler->filter('form[name="category_product"]')->form(['category_product[name]' => 'T-1000']));
        self::assertResponseRedirects($base);

        $crawler = $this->client->request('GET', $base);
        self::assertCount(1, $crawler->filter(\sprintf('a[href="%s/new"]', $base)));
        self::assertCount(1, $crawler->filter(\sprintf('a[href="%s/%d/edit"]', $base, $product->getId())));
        $deleteUrl = $base . '/' . $product->getId() . '/delete';
        $token = (string) $crawler->filter(\sprintf('[data-gm-delete-url="%s"]', $deleteUrl))->attr('data-gm-delete-token');

        // #[IsCsrfTokenValid]: an InvalidCsrfTokenException, the firewall's entry point.
        $this->client->request('POST', $deleteUrl, ['_token' => 'invalid']);
        self::assertResponseRedirects('/admin/login');

        $this->client->request('POST', $deleteUrl, ['_token' => $token]);
        self::assertResponseRedirects($base);
        $entityManager->clear();
        self::assertNull($entityManager->find(Product::class, $product->getId()));
    }

    public function testFormColumnsRenderTheFormIncludes(): void
    {
        $user = $this->fixtures->user('form-include@example.com', superAdmin: true);
        $this->client->loginUser($user, 'admin');

        // Two-column form: each column ends with its includes.
        $crawler = $this->client->request('GET', '/admin/users/' . $user->getId() . '/edit');
        self::assertResponseIsSuccessful();
        self::assertSame((string) $user->getId(), $crawler->filter('.col-lg-8 > .gm-test-form-include')->text());
        self::assertSame('user', $crawler->filter('.col-lg-4 > .gm-test-form-include')->attr('data-form'));
        self::assertSame('gm-test-form-include', $crawler->filter('.col-lg-8 > *')->last()->attr('class'));

        // One-column form: a sidebar include adds the right column.
        $crawler = $this->client->request('GET', '/admin/permissions/new');
        self::assertResponseIsSuccessful();
        self::assertSame('new', $crawler->filter('.col-lg-4 > .gm-test-form-include')->text());
        self::assertCount(1, $crawler->filter('.crud-form .col-lg-8 #permission_name'));

        // Without include nor sidebar content: one full-width column.
        $crawler = $this->client->request('GET', '/admin/roles/new');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.crud-form .col-lg-4'));
        self::assertCount(1, $crawler->filter('.crud-form .col-12 #role_name'));
        // The translation domain applies inside the embedded layout.
        self::assertSelectorTextNotContains('.crud-form', 'role.name_s');
        self::assertSelectorTextNotContains('.crud-form', 'role.field.permissions');

        // The profile has its own slots.
        $crawler = $this->client->request('GET', '/admin/profile');
        self::assertSame('profile', $crawler->filter('.col-lg-8 > .gm-test-form-include')->attr('data-form'));
    }

    public function testSidebarRendersTheAdminIncludes(): void
    {
        $this->client->loginUser($this->fixtures->user('include@example.com', ['view users']), 'admin');

        $crawler = $this->client->request('GET', '/admin/');

        self::assertSame('include@example.com', $crawler->filter('#gm-sidebar .gm-test-sidebar-include')->text());

        // `sidebar_bottom`: after the menu, right before the user menu.
        $sidebar = $crawler->filter('#gm-sidebar')->html();
        $bottom = strpos($sidebar, 'gm-test-sidebar-bottom-include');
        self::assertNotFalse($bottom);
        self::assertGreaterThan(strpos($sidebar, 'id="gm-sidebar-menu"'), $bottom);
        self::assertLessThan(strpos($sidebar, 'sidebar-profile-toggle'), $bottom);
        // Separators: under the logo, above the bottom includes, above the user menu.
        self::assertCount(3, $crawler->filter('#gm-sidebar > hr'));
    }

    public function testNoSidebarBottomSeparatorWhenItsTemplatesRenderNothing(): void
    {
        $this->client->loginUser($this->fixtures->user('empty-include@example.com', ['view users']), 'admin');

        $crawler = $this->client->request('GET', '/admin/?bottom=hide');

        self::assertCount(0, $crawler->filter('#gm-sidebar .gm-test-sidebar-bottom-include'));
        self::assertCount(2, $crawler->filter('#gm-sidebar > hr'), 'Only the logo and user menu separators.');
    }

    public function testHeadRendersTheAdminIncludesAfterTheAdminAssets(): void
    {
        $crawler = $this->client->request('GET', '/admin/login');

        $head = $crawler->filter('head')->html();
        $include = strpos($head, 'gm-test-head-include');
        self::assertNotFalse($include);
        self::assertGreaterThan(strpos($head, 'gingerminds-core/styles/admin'), $include);

        $this->client->loginUser($this->fixtures->user('head@example.com', ['view users']), 'admin');
        $crawler = $this->client->request('GET', '/admin/');

        self::assertCount(1, $crawler->filter('head meta[name="gm-test-head-include"]'));
    }

    public function testPermissionsAreEnforced(): void
    {
        $this->client->loginUser($this->fixtures->user('limited@example.com', ['view users']), 'admin');

        $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/admin/users/new');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/roles');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEditAndDeleteResolveTheEntityBeforeCheckingAccess(): void
    {
        $permission = $this->fixtures->permission('view gadgets');
        $this->client->loginUser($this->fixtures->user('reader@example.com', ['view permissions']), 'admin');

        // ResourceEntityValueResolver: an unknown id is a 404, whatever the permissions.
        $this->client->request('GET', '/admin/permissions/999999/edit');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/admin/permissions/999999/delete');
        self::assertResponseStatusCodeSame(404);

        // #[IsGranted('EDIT'|'DELETE', subject: 'entity')] on the resolved entity, before the CSRF check.
        $this->client->request('GET', '/admin/permissions/' . $permission->getId() . '/edit');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/admin/permissions/' . $permission->getId() . '/delete', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull(self::getContainer()->get(EntityManagerInterface::class)->find(Permission::class, $permission->getId()));
    }

    public function testEditPageOfAnAuthorizedUser(): void
    {
        $permission = $this->fixtures->permission('view widgets');
        $this->client->loginUser($this->fixtures->user('editor@example.com', ['view permissions', 'edit permissions']), 'admin');

        $crawler = $this->client->request('GET', '/admin/permissions/' . $permission->getId() . '/edit');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->filter('form[name="permission"]')->form(['permission[name]' => 'view gizmos']));
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'view gizmos');
    }

    public function testCreateRole(): void
    {
        $this->client->loginUser($this->fixtures->user('creator@example.com', superAdmin: true), 'admin');
        $permission = $this->fixtures->permission('view widgets');

        $crawler = $this->client->request('GET', '/admin/roles/new');
        $form = $crawler->filter('form[name="role"]')->form();
        $form['role[name]'] = 'Widget manager';
        $form['role[permissions]'][0]->tick();
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/roles');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Widget manager');

        $role = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Role::class)->findOneBy(['name' => 'Widget manager']);
        self::assertNotNull($role);
        self::assertSame($permission->getId(), $role->getPermissions()->first()->getId());
    }

    public function testRedirectAfterSaveFollowsTheConfiguration(): void
    {
        $this->client->loginUser($this->fixtures->user('redirect@example.com', superAdmin: true), 'admin');
        $role = $this->fixtures->role('Redirected');

        // Global `edit: index`
        $crawler = $this->client->request('GET', '/admin/roles/' . $role->getId() . '/edit');
        $this->client->submit($crawler->filter('form[name="role"]')->form(['role[name]' => 'Redirected again']));
        self::assertResponseRedirects('/admin/roles');

        // `permission` resource override `redirect_after_new: edit`
        $crawler = $this->client->request('GET', '/admin/permissions/new');
        $this->client->submit($crawler->filter('form[name="permission"]')->form(['permission[name]' => 'view redirects']));

        $permission = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Permission::class)->findOneBy(['name' => 'view redirects']);
        self::assertNotNull($permission);
        self::assertResponseRedirects('/admin/permissions/' . $permission->getId() . '/edit');
    }

    public function testInvalidFormIsRedisplayedWith422(): void
    {
        $this->client->loginUser($this->fixtures->user('invalid@example.com', superAdmin: true), 'admin');

        $crawler = $this->client->request('GET', '/admin/permissions/new');
        $this->client->submit($crawler->filter('form[name="permission"]')->form(['permission[name]' => '']));

        self::assertResponseStatusCodeSame(422);
    }

    public function testDeleteRequiresAValidCsrfToken(): void
    {
        $this->client->loginUser($this->fixtures->user('deleter@example.com', superAdmin: true), 'admin');
        $role = $this->fixtures->role('To delete');

        // #[IsCsrfTokenValid]: an InvalidCsrfTokenException, the firewall's entry point.
        $this->client->request('POST', '/admin/roles/' . $role->getId() . '/delete', ['_token' => 'invalid']);
        self::assertResponseRedirects('/admin/login');
        self::assertNotNull(self::getContainer()->get(EntityManagerInterface::class)->find(Role::class, $role->getId()));

        $csrf = $this->deleteToken('/admin/roles', '/admin/roles/' . $role->getId() . '/delete');

        $this->client->request('POST', '/admin/roles/' . $role->getId() . '/delete', ['_token' => $csrf]);
        self::assertResponseRedirects('/admin/roles');
        self::assertNull(self::getContainer()->get(EntityManagerInterface::class)->find(Role::class, $role->getId()));
    }

    public function testProfileUpdate(): void
    {
        $this->client->loginUser($this->fixtures->user('profile@example.com'), 'admin');

        $crawler = $this->client->request('GET', '/admin/profile');
        $form = $crawler->filter('form[name="profile"]')->form();
        $form['profile[contributorLastname]'] = 'Updated';
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/profile');
        $this->client->followRedirect();
        self::assertInputValueSame('profile[contributorLastname]', 'Updated');
    }

    public function testProfilePasswordChangeAloneIsHashed(): void
    {
        $this->client->loginUser($this->fixtures->user('password@example.com'), 'admin');

        // Only the unmapped plain password changes: hashed on flush all the same.
        $crawler = $this->client->request('GET', '/admin/profile');
        $form = $crawler->filter('form[name="profile"]')->form();
        $form['profile[password][first]'] = 'changed-password';
        $form['profile[password][second]'] = 'changed-password';
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/profile');

        $user = self::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => 'password@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertNull($user->getPlainPassword());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'changed-password'));
    }

    public function testUserFormCreatesTheUserWithANewContributor(): void
    {
        $this->client->loginUser($this->fixtures->user('admin-contributor@example.com', superAdmin: true), 'admin');

        $crawler = $this->client->request('GET', '/admin/users/new');
        $form = $crawler->filter('form[name="user"]')->form();
        $form['user[email]'] = 'grace@example.com';
        $form['user[password][first]'] = 'grace-password';
        $form['user[password][second]'] = 'grace-password';
        $form['user[contributorId]']->setValue(UserType::NEW_CONTRIBUTOR);
        $form['user[contributorFirstname]'] = 'Grace';
        $form['user[contributorLastname]'] = 'Hopper';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'grace@example.com']);

        self::assertInstanceOf(User::class, $user);
        self::assertSame('Grace Hopper', $user->getContributor()?->getFullName());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'grace-password'));
    }

    public function testAutocompleteEndpoint(): void
    {
        $this->client->loginUser($this->fixtures->user('autocomplete@example.com', ['view categories']), 'admin');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new Category('Shoes'));
        $entityManager->persist(new Category('Shirts'));
        $entityManager->persist(new Category('Hats'));
        $entityManager->flush();

        $this->client->request('GET', '/admin/_autocomplete/category?query=sh');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['Shoes', 'Shirts'], array_column($data['results'], 'text'));
        self::assertNull($data['next_page']);

        $this->client->request('GET', '/admin/_autocomplete/role?query=a');
        self::assertResponseStatusCodeSame(403);
    }

    public function testSuperAdminRoleCannotBeDeletedFromTheAdmin(): void
    {
        $user = $this->fixtures->user('super@example.com', superAdmin: true);
        $this->client->loginUser($user, 'admin');
        $role = $user->getRoleEntities()->first();

        $csrf = $this->deleteToken('/admin/roles', '/admin/roles/' . $role->getId() . '/delete');

        $this->client->request('POST', '/admin/roles/' . $role->getId() . '/delete', ['_token' => $csrf]);

        self::assertResponseRedirects('/admin/roles');
        self::assertNotNull(self::getContainer()->get(EntityManagerInterface::class)->find(Role::class, $role->getId()));
    }

    /**
     * SQL queries run by one list page.
     */
    private function listQueryCount(string $uri): int
    {
        // Nothing loaded yet: every user of the page comes from the database.
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $queries = self::getContainer()->get('doctrine.debug_data_holder');
        $queries->reset();

        $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful();

        return \count($queries->getData()['default'] ?? []);
    }

    /**
     * CSRF token of the delete button rendered by the list page for $deleteUrl.
     */
    private function deleteToken(string $listUrl, string $deleteUrl): string
    {
        $button = $this->client->request('GET', $listUrl)->filter(\sprintf('[data-gm-delete-url="%s"]', $deleteUrl));
        self::assertCount(1, $button, 'The list must expose a delete button with its CSRF token.');

        return (string) $button->attr('data-gm-delete-token');
    }
}
