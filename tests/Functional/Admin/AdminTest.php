<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Admin;

use Doctrine\ORM\EntityManagerInterface;
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
     * CSRF token of the delete button rendered by the list page for $deleteUrl.
     */
    private function deleteToken(string $listUrl, string $deleteUrl): string
    {
        $button = $this->client->request('GET', $listUrl)->filter(\sprintf('[data-gm-delete-url="%s"]', $deleteUrl));
        self::assertCount(1, $button, 'The list must expose a delete button with its CSRF token.');

        return (string) $button->attr('data-gm-delete-token');
    }
}
