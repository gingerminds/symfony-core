<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Api;

use Gingerminds\CoreBundle\Tests\Functional\ApiTestCase;

/**
 * Resource voters on the core API resources (`view|edit|delete {resource}`,
 * `manage roles`, super-admin bypass).
 */
final class PermissionsTest extends ApiTestCase
{
    public function testUserWithoutPermissionIsDenied(): void
    {
        $token = $this->fixtures->token($this->fixtures->user('nobody@example.com'));

        $this->api('GET', '/api/users', $token);
        $this->assertStatus(403);

        $this->api('GET', '/api/roles', $token);
        $this->assertStatus(403);
    }

    public function testViewPermissionDoesNotAllowWrites(): void
    {
        $token = $this->fixtures->token($this->fixtures->user('viewer@example.com', ['view permissions']));

        $this->api('GET', '/api/permissions', $token);
        $this->assertStatus(200);

        $this->api('POST', '/api/permissions', $token, ['name' => 'view things']);
        $this->assertStatus(403);
    }

    public function testUserCanAlwaysReadAndEditOwnAccount(): void
    {
        $user = $this->fixtures->user('self@example.com');
        $token = $this->fixtures->token($user);

        $data = $this->api('GET', '/api/users/' . $user->getId(), $token);
        $this->assertStatus(200);
        self::assertSame('self@example.com', $data['email']);

        $this->api('PATCH', '/api/users/' . $user->getId(), $token, ['email' => 'renamed@example.com'], 'application/merge-patch+json');
        $this->assertStatus(200);

        $this->api('DELETE', '/api/users/' . $user->getId(), $token);
        $this->assertStatus(403);
    }

    public function testRolesRequireManageRoles(): void
    {
        $token = $this->fixtures->token($this->fixtures->user('roles@example.com', ['manage roles']));

        $this->api('GET', '/api/roles', $token);
        $this->assertStatus(200);
    }

    public function testSuperAdminIsGrantedEverything(): void
    {
        $token = $this->fixtures->token($this->fixtures->user('root@example.com', superAdmin: true));

        foreach (['/api/users', '/api/contributors', '/api/roles', '/api/permissions'] as $uri) {
            $this->api('GET', $uri, $token);
            $this->assertStatus(200);
        }
    }
}
