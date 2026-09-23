<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Api;

use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Tests\Functional\ApiTestCase;

/**
 * Writes go through the resource form: same validation and repository
 * hooks as the admin.
 */
final class WriteTest extends ApiTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->fixtures->token($this->fixtures->user('root@example.com', superAdmin: true));
    }

    public function testCreateRoleWithPermissions(): void
    {
        $permission = $this->fixtures->permission('view reports');

        $data = $this->api('POST', '/api/roles', $this->token, [
            'name' => 'Reporter',
            'permissions' => [(string) $permission->getId()],
        ]);

        $this->assertStatus(201);
        self::assertSame('Reporter', $data['name']);
        self::assertSame('view reports', $data['permissions'][0]['name']);
        self::assertFalse($data['isDefault']);
    }

    public function testValidationErrorsAreReturnedAs422(): void
    {
        $data = $this->api('POST', '/api/roles', $this->token, ['name' => '']);

        $this->assertStatus(422);
        self::assertSame('name', $data['violations'][0]['propertyPath']);
    }

    public function testUniqueConstraintIsEnforced(): void
    {
        $this->fixtures->role('Duplicate');

        $data = $this->api('POST', '/api/roles', $this->token, ['name' => 'Duplicate']);

        $this->assertStatus(422);
        self::assertSame('name', $data['violations'][0]['propertyPath']);
    }

    public function testPatchOnlyUpdatesSentFields(): void
    {
        $role = $this->fixtures->role('Patched', ['view patched', 'edit patched']);

        $data = $this->api('PATCH', '/api/roles/' . $role->getId(), $this->token, ['isExternal' => true], 'application/merge-patch+json');

        $this->assertStatus(200);
        self::assertTrue($data['isExternal']);
        self::assertCount(2, $data['permissions']);
    }

    public function testOnlyOneDefaultRolePerAudience(): void
    {
        $first = $this->fixtures->role('First default');
        $this->api('PATCH', '/api/roles/' . $first->getId(), $this->token, ['isDefault' => true], 'application/merge-patch+json');
        $this->assertStatus(200);

        $data = $this->api('POST', '/api/roles', $this->token, ['name' => 'Second default', 'isDefault' => true]);
        $this->assertStatus(201);
        self::assertTrue($data['isDefault']);

        $first = $this->api('GET', '/api/roles/' . $first->getId(), $this->token);
        self::assertFalse($first['isDefault']);
    }

    public function testSuperAdminRoleCannotBeDeleted(): void
    {
        $role = self::getContainer()->get('doctrine')->getRepository(Role::class)->findOneBy(['name' => 'Super-Admin']);

        $data = $this->api('DELETE', '/api/roles/' . $role?->getId(), $this->token, locale: 'fr');

        $this->assertStatus(422);
        self::assertSame('Le rôle Super-Admin ne peut pas être supprimé.', $data['detail']);
    }

    public function testCreateUserWithNewContributorAndHashedPassword(): void
    {
        $role = $this->fixtures->role('Member');

        $data = $this->api('POST', '/api/users', $this->token, [
            'email' => 'new@example.com',
            'password' => ['first' => 'secret-password', 'second' => 'secret-password'],
            'roles' => [(string) $role->getId()],
            'contributorId' => '__new__',
            'contributorFirstname' => 'Ada',
            'contributorLastname' => 'Lovelace',
        ]);

        $this->assertStatus(201);
        self::assertSame('Member', $data['roles'][0]['name']);
        self::assertSame('Lovelace', $data['contributor']['lastname']);

        $login = $this->api('POST', '/api/login', json: ['email' => 'new@example.com', 'password' => 'secret-password']);
        $this->assertStatus(200);
        self::assertArrayHasKey('token', $login);
    }

    public function testCreateUserRequiresPasswordConfirmation(): void
    {
        $this->api('POST', '/api/users', $this->token, [
            'email' => 'mismatch@example.com',
            'password' => ['first' => 'secret-password', 'second' => 'other-password'],
        ]);

        $this->assertStatus(422);
    }

    public function testDeletingAContributorKeepsItsUser(): void
    {
        $user = $this->fixtures->user('delete@example.com');
        $contributorId = $user->getContributor()?->getId();

        $this->api('DELETE', '/api/contributors/' . $contributorId, $this->token);
        $this->assertStatus(204);

        $this->api('GET', '/api/contributors/' . $contributorId, $this->token);
        $this->assertStatus(404);

        $data = $this->api('GET', '/api/users/' . $user->getId(), $this->token);
        $this->assertStatus(200);
        self::assertArrayNotHasKey('contributor', $data);
    }

    public function testUserCanGetANewContributorAfterDeletion(): void
    {
        $user = $this->fixtures->user('relink@example.com');
        $this->api('DELETE', '/api/contributors/' . $user->getContributor()?->getId(), $this->token);

        $data = $this->api('PATCH', '/api/users/' . $user->getId(), $this->token, [
            'contributorId' => '__new__',
            'contributorFirstname' => 'New',
            'contributorLastname' => 'Profile',
        ], 'application/merge-patch+json');

        $this->assertStatus(200);
        self::assertSame('Profile', $data['contributor']['lastname']);
    }
}
