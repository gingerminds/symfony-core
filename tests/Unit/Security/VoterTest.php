<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\Security;

use Gingerminds\CoreBundle\Entity\Permission\Permission;
use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Entity\User\BaseUser;
use Gingerminds\CoreBundle\Entity\User\User;
use Gingerminds\CoreBundle\GingermindsCoreBundle;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\CoreBundle\Security\Voter\PermissionNameVoter;
use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Gingerminds\CoreBundle\Security\Voter\SuperAdminVoter;
use Gingerminds\CoreBundle\Tests\Application\Entity\Category;
use Gingerminds\CoreBundle\Tests\Application\Override\User as OverriddenUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class VoterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function crudPermissions(): iterable
    {
        foreach (['user' => 'users', 'contributor' => 'contributors', 'permission' => 'permissions', 'category' => 'categories'] as $resource => $plural) {
            yield $resource . ' VIEW' => [$resource, 'VIEW', 'view ' . $plural];
            yield $resource . ' CREATE' => [$resource, 'CREATE', 'edit ' . $plural];
            yield $resource . ' EDIT' => [$resource, 'EDIT', 'edit ' . $plural];
            yield $resource . ' DELETE' => [$resource, 'DELETE', 'delete ' . $plural];
        }
    }

    #[DataProvider('crudPermissions')]
    public function testEachAttributeRequiresItsPermission(string $resource, string $attribute, string $permission): void
    {
        $voter = new ResourceVoter($this->registry());
        $entityClass = $this->registry()->getEntityClass($resource);
        $others = array_values(array_filter(
            ['view', 'edit', 'delete'],
            static fn (string $action): bool => !str_starts_with($permission, $action . ' '),
        ));
        $plural = substr($permission, strpos($permission, ' ') + 1);
        $withOthers = $this->token($this->user(array_map(static fn (string $action): string => $action . ' ' . $plural, $others)));
        $with = $this->token($this->user([$permission]));

        foreach ([$resource, $entityClass, new $entityClass()] as $subject) {
            self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($with, $subject, [$attribute]));
            self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($withOthers, $subject, [$attribute]));
            self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new NullToken(), $subject, [$attribute]));
        }
    }

    public function testRolesRequireManageRoles(): void
    {
        $voter = new ResourceVoter($this->registry());

        foreach (ResourceVoter::ATTRIBUTES as $attribute) {
            self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->token($this->user(['view roles', 'edit roles', 'delete roles'])), 'role', [$attribute]));
            self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->token($this->user(['manage roles'])), new Role(), [$attribute]));
        }

        self::assertSame(['manage roles'], $this->registry()->get('role')->permissionNames());
        self::assertSame(['view users', 'edit users', 'delete users'], $this->registry()->get('user')->permissionNames());
    }

    public function testUserViewsAndEditsButNeverDeletesTheirOwnAccount(): void
    {
        $voter = new ResourceVoter($this->registry());
        $self = $this->user([], 1);
        $other = $this->user([], 2);
        $token = $this->token($self);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $self, ['VIEW']));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $self, ['EDIT']));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $other, ['EDIT']));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->token($this->user(['delete users'], 1)), $self, ['DELETE']));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->token($this->user(['delete users'], 1)), $other, ['DELETE']));
    }

    public function testAbstainsOutsideTheResources(): void
    {
        $voter = new ResourceVoter($this->registry());
        $token = $this->token($this->user(['view users']));

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, 'unknown', ['VIEW']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new \stdClass(), ['VIEW']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, 'user', ['view users']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, null, ['VIEW']));

        // CacheableVoterInterface: the access decision manager skips the voter by attribute / subject type.
        self::assertTrue($voter->supportsAttribute('EDIT'));
        self::assertFalse($voter->supportsAttribute('view users'));
        self::assertTrue($voter->supportsType('string'));
        self::assertTrue($voter->supportsType(Category::class));
        self::assertFalse($voter->supportsType(\stdClass::class));
        self::assertFalse($voter->supportsType('null'));
    }

    public function testOverriddenEntityIsTheResourceSubject(): void
    {
        $registry = $this->registry(['user' => OverriddenUser::class]);
        $voter = new ResourceVoter($registry);
        $token = $this->token($this->user(['edit users']));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, new OverriddenUser(), ['EDIT']));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, OverriddenUser::class, ['CREATE']));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, 'user', ['CREATE']));
        self::assertTrue($voter->supportsType(OverriddenUser::class));
        // The bundle class is no resource entity any more.
        self::assertFalse($voter->supportsType(User::class));
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
        // Cached support (CacheableVoterInterface).
        self::assertTrue($voter->supportsAttribute('view dashboard'));
        self::assertFalse($voter->supportsAttribute('EDIT'));
        self::assertTrue($voter->supportsType('null'));
        self::assertFalse($voter->supportsType(\stdClass::class));
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
     * The core resources and the test `category`, as ResourceRegistryPass registers them.
     *
     * @param array<string, class-string> $entities entity overrides
     */
    private function registry(array $entities = []): ResourceRegistry
    {
        $resources = [];

        foreach ([...GingermindsCoreBundle::RESOURCES, 'category' => ['entity' => Category::class, 'permission' => 'categories']] as $name => $resource) {
            $resources[$name] = [
                'entity' => $entities[$name] ?? $resource['entity'],
                'path' => $name,
                'permission' => $resource['permission'],
                'route_prefix' => 'admin_' . $name,
                'translation_prefix' => $name,
                'translation_domain' => 'messages',
                'template_prefix' => 'admin/' . $name,
                'permissions' => $resource['permissions'] ?? [],
            ];
        }

        return new ResourceRegistry($resources);
    }

    /**
     * @param list<string> $permissions
     */
    private function user(array $permissions, ?int $id = null): User
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
        new \ReflectionProperty(BaseUser::class, 'id')->setValue($user, $id);

        return $user;
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }
}
