<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Entity\Permission\Permission;
use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Tests\Application\Entity\Category;
use Gingerminds\CoreBundle\Tests\Functional\ApiTestCase;
use Gingerminds\CoreBundle\Tests\Functional\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;

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

        $this->client->request('POST', '/admin/roles/' . $role->getId() . '/delete', ['_token' => 'invalid']);
        self::assertResponseRedirects('/admin/roles');
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
