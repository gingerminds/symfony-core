<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Api;

use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Tests\Functional\ApiTestCase;

/**
 * Native API Platform writes: the payload is deserialized (relations by id or IRI), validated
 * by the entity constraints (422 + `violations`), then persisted by the Doctrine processor
 * inside the repository save hooks, as the admin.
 */
final class WriteTest extends ApiTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->fixtures->token($this->fixtures->user('root@example.com', superAdmin: true));
    }

    public function testCreateRoleWithPermissionsById(): void
    {
        $reports = $this->fixtures->permission('view reports');
        $exports = $this->fixtures->permission('export reports');

        $data = $this->api('POST', '/api/roles', $this->token, [
            'name' => 'Reporter',
            'permissions' => [$reports->getId(), (string) $exports->getId()],
        ]);

        $this->assertStatus(201);
        self::assertSame('Reporter', $data['name']);
        self::assertEqualsCanonicalizing(['view reports', 'export reports'], array_column($data['permissions'], 'name'));
        self::assertEqualsCanonicalizing([$reports->getId(), $exports->getId()], array_column($data['permissions'], 'id'));
        self::assertFalse($data['isDefault']);
    }

    public function testCreateRoleWithPermissionsByIri(): void
    {
        $permission = $this->fixtures->permission('view reports');

        $data = $this->api('POST', '/api/roles', $this->token, [
            'name' => 'Reporter',
            'permissions' => ['/api/permissions/' . $permission->getId()],
        ]);

        $this->assertStatus(201);
        self::assertSame('view reports', $data['permissions'][0]['name']);
    }

    public function testUnknownRelationIdIsRejected(): void
    {
        $data = $this->api('POST', '/api/roles', $this->token, [
            'name' => 'Reporter',
            'permissions' => [999999],
        ]);

        $this->assertStatus(400);
        self::assertStringContainsString('/api/permissions/999999', (string) $data['detail']);
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
        self::assertSame('name', $data['violations'][0]['propertyPath']);
        self::assertSame('Le rôle Super-Admin ne peut pas être supprimé.', $data['violations'][0]['message']);

        $this->api('GET', '/api/roles/' . $role?->getId(), $this->token);
        $this->assertStatus(200);
    }

    public function testCreateUserWithNewContributorAndHashedPassword(): void
    {
        $role = $this->fixtures->role('Member');

        $data = $this->api('POST', '/api/users', $this->token, [
            'email' => 'new@example.com',
            'plainPassword' => 'secret-password',
            'roles' => [$role->getId()],
            'contributor' => ['firstname' => 'Ada', 'lastname' => 'Lovelace'],
        ]);

        $this->assertStatus(201);
        self::assertSame('Member', $data['roles'][0]['name']);
        self::assertSame($role->getId(), $data['roles'][0]['id']);
        self::assertSame('Lovelace', $data['contributor']['lastname']);
        self::assertIsInt($data['contributor']['id']);
        self::assertArrayNotHasKey('plainPassword', $data);
        self::assertArrayNotHasKey('password', $data);

        $login = $this->api('POST', '/api/login', json: ['email' => 'new@example.com', 'password' => 'secret-password']);
        $this->assertStatus(200);
        self::assertArrayHasKey('token', $login);
    }

    public function testCreateUserRequiresAPassword(): void
    {
        $data = $this->api('POST', '/api/users', $this->token, ['email' => 'no-password@example.com']);

        $this->assertStatus(422);
        self::assertSame('plainPassword', $data['violations'][0]['propertyPath']);
    }

    public function testANewContributorIsValidated(): void
    {
        $data = $this->api('POST', '/api/users', $this->token, [
            'email' => 'invalid-contributor@example.com',
            'plainPassword' => 'secret-password',
            'contributor' => ['firstname' => 'Ada'],
        ]);

        $this->assertStatus(422);
        self::assertSame('contributor.lastname', $data['violations'][0]['propertyPath']);
    }

    public function testUserRolesAreReplacedByIdOrIri(): void
    {
        $user = $this->fixtures->user('roles@example.com', ['view roles']);
        $member = $this->fixtures->role('Member');
        $editor = $this->fixtures->role('Editor');

        $data = $this->api('PATCH', '/api/users/' . $user->getId(), $this->token, [
            'roles' => [(string) $member->getId(), '/api/roles/' . $editor->getId()],
        ], 'application/merge-patch+json');

        $this->assertStatus(200);
        self::assertEqualsCanonicalizing(['Member', 'Editor'], array_column($data['roles'], 'name'));
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
            'contributor' => ['firstname' => 'New', 'lastname' => 'Profile'],
        ], 'application/merge-patch+json');

        $this->assertStatus(200);
        self::assertSame('Profile', $data['contributor']['lastname']);
    }

    public function testANestedContributorUpdatesTheUserContributor(): void
    {
        $user = $this->fixtures->user('api-nested@example.com');
        $contributorId = $user->getContributor()?->getId();

        // JSON Merge Patch: the nested document is merged into the current contributor.
        $data = $this->api('PATCH', '/api/users/' . $user->getId(), $this->token, [
            'contributor' => ['lastname' => 'Jackson'],
        ], 'application/merge-patch+json');

        $this->assertStatus(200);
        self::assertSame($contributorId, $data['contributor']['id']);
        self::assertSame('Jackson', $data['contributor']['lastname']);
        self::assertSame('First', $data['contributor']['firstname']);
    }

    public function testUserContributorIsReplacedByANewOne(): void
    {
        $user = $this->fixtures->user('api-replaced@example.com');
        $oldId = $user->getContributor()?->getId();

        $data = $this->api('POST', '/api/contributors', $this->token, [
            'firstname' => 'Mary',
            'lastname' => 'Jackson',
            'user' => $user->getId(),
        ]);

        $this->assertStatus(201);
        self::assertNotSame($oldId, $data['id']);

        $data = $this->api('GET', '/api/users/' . $user->getId(), $this->token);
        self::assertSame('Jackson', $data['contributor']['lastname']);

        $old = $this->api('GET', '/api/contributors/' . $oldId, $this->token);
        $this->assertStatus(200);
        self::assertArrayNotHasKey('user', $old);
    }

    public function testUserContributorIsReplacedByAnExistingOneById(): void
    {
        $user = $this->fixtures->user('api-switched@example.com');
        $oldId = $user->getContributor()?->getId();
        $other = $this->fixtures->user('other@example.com');
        $otherContributorId = $other->getContributor()?->getId();
        // A free contributor: its user is deleted (contributors.user_id SET NULL).
        $this->api('DELETE', '/api/users/' . $other->getId(), $this->token);
        $this->assertStatus(204);

        $data = $this->api('PATCH', '/api/users/' . $user->getId(), $this->token, [
            'contributor' => $otherContributorId,
        ], 'application/merge-patch+json');

        $this->assertStatus(200);
        self::assertSame($otherContributorId, $data['contributor']['id']);

        $old = $this->api('GET', '/api/contributors/' . $oldId, $this->token);
        $this->assertStatus(200);
        self::assertArrayNotHasKey('user', $old);
    }

    public function testContributorUserIsWrittenById(): void
    {
        $user = $this->fixtures->user('linked@example.com');
        $this->api('DELETE', '/api/contributors/' . $user->getContributor()?->getId(), $this->token);

        $data = $this->api('POST', '/api/contributors', $this->token, [
            'firstname' => 'Grace',
            'lastname' => 'Hopper',
            'user' => (string) $user->getId(),
        ]);

        $this->assertStatus(201);
        self::assertSame($user->getId(), $data['user']['id']);
    }
}
