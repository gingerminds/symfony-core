<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\Security;

use Gingerminds\CoreBundle\Entity\Permission\Permission;
use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Entity\User\User;
use Gingerminds\CoreBundle\Security\Voter\PermissionNameVoter;
use Gingerminds\CoreBundle\Security\Voter\Role\RoleVoter;
use Gingerminds\CoreBundle\Security\Voter\SuperAdminVoter;
use Gingerminds\CoreBundle\Security\Voter\User\UserVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class VoterTest extends TestCase
{
    public function testResourceVoterMapsAttributesToPermissions(): void
    {
        $voter = new UserVoter();
        $token = $this->token($this->user(['view users', 'edit users']));
        $other = new User();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, 'user', ['VIEW']));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, User::class, ['CREATE']));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $other, ['EDIT']));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $other, ['DELETE']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, 'role', ['VIEW']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, 'user', ['PUBLISH']));
    }

    public function testRoleVoterRequiresManageRoles(): void
    {
        $voter = new RoleVoter();

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->token($this->user(['view roles'])), 'role', ['VIEW']));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->token($this->user(['manage roles'])), new Role(), ['DELETE']));
    }

    public function testPermissionVoterOnlySupportsPermissionNames(): void
    {
        $voter = new PermissionNameVoter();
        $token = $this->token($this->user(['view dashboard']));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, null, ['view dashboard']));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, null, ['edit users']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, null, ['ROLE_ADMIN']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, null, ['IS_AUTHENTICATED']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new \stdClass(), ['view dashboard']));
    }

    public function testSuperAdminVoter(): void
    {
        $voter = new SuperAdminVoter();
        $superAdmin = new User();
        $role = new Role();
        $role->setName(User::SUPER_ADMIN_ROLE);
        $superAdmin->addRoleEntity($role);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->token($superAdmin), null, ['anything']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($this->token($this->user([])), null, ['anything']));
        self::assertContains('ROLE_SUPER_ADMIN', $superAdmin->getRoles());
    }

    /**
     * @param list<string> $permissions
     */
    private function user(array $permissions): User
    {
        $role = new Role();
        $role->setName('Test');

        foreach ($permissions as $name) {
            $permission = new Permission();
            $permission->setName($name);
            $role->addPermission($permission);
        }

        $user = new User();
        $user->setEmail('test@example.com');
        $user->addRoleEntity($role);

        return $user;
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }
}
