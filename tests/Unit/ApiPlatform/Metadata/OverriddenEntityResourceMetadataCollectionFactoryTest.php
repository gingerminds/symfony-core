<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\ApiPlatform\Metadata;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use Gingerminds\CoreBundle\ApiPlatform\Metadata\CoreResourceNameCollectionFactory;
use Gingerminds\CoreBundle\ApiPlatform\Metadata\OverriddenEntityResourceMetadataCollectionFactory;
use Gingerminds\CoreBundle\Entity\Permission\Permission;
use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Entity\User\User;
use PHPUnit\Framework\TestCase;

final class OverriddenEntityResourceMetadataCollectionFactoryTest extends TestCase
{
    private const string PROJECT_USER = 'App\Entity\User';
    private const string PROJECT_ROLE = 'App\Entity\Role';

    public function testTheProjectEntityInheritsTheBundleResource(): void
    {
        $resource = $this->factory()->create(self::PROJECT_USER)[0];

        self::assertSame(self::PROJECT_USER, $resource->getClass());
        self::assertSame('User', $resource->getShortName());

        foreach ($resource->getOperations() ?? [] as $operation) {
            self::assertSame(self::PROJECT_USER, $operation->getClass());
        }
    }

    public function testTheProjectDeclarationReplacesTheBundleOne(): void
    {
        $collection = $this->factory()->create(self::PROJECT_ROLE);

        self::assertCount(1, $collection);
        self::assertSame('ProjectRole', $collection[0]->getShortName());
        self::assertCount(1, $collection[0]->getOperations() ?? []);
    }

    public function testOtherClassesAreLeftAlone(): void
    {
        self::assertSame('Permission', $this->factory()->create(Permission::class)[0]->getShortName());
    }

    public function testTheProjectEntityReplacesTheBundleOneAmongTheResourceNames(): void
    {
        $names = $this->createStub(ResourceNameCollectionFactoryInterface::class);
        $names->method('create')->willReturn(new ResourceNameCollection([User::class, Role::class, self::PROJECT_ROLE, Permission::class]));
        $factory = new CoreResourceNameCollectionFactory($names, [User::class => self::PROJECT_USER, Role::class => self::PROJECT_ROLE], [Permission::class]);

        self::assertSame([self::PROJECT_USER, self::PROJECT_ROLE], iterator_to_array($factory->create()));
    }

    private function factory(): OverriddenEntityResourceMetadataCollectionFactory
    {
        $resources = [
            User::class => new ApiResource(class: User::class, shortName: 'User', operations: [
                'get_collection' => new GetCollection(class: User::class),
                'get' => new Get(class: User::class),
            ]),
            Role::class => new ApiResource(class: Role::class, shortName: 'Role', operations: [
                'get_collection' => new GetCollection(class: Role::class),
                'get' => new Get(class: Role::class),
            ]),
            self::PROJECT_ROLE => new ApiResource(class: self::PROJECT_ROLE, shortName: 'ProjectRole', operations: [
                'get' => new Get(class: self::PROJECT_ROLE),
            ]),
            Permission::class => new ApiResource(class: Permission::class, shortName: 'Permission'),
        ];
        $inner = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $inner->method('create')->willReturnCallback(static fn (string $class): ResourceMetadataCollection => new ResourceMetadataCollection($class, isset($resources[$class]) ? [$resources[$class]] : []));

        return new OverriddenEntityResourceMetadataCollectionFactory($inner, [User::class => self::PROJECT_USER, Role::class => self::PROJECT_ROLE]);
    }
}
