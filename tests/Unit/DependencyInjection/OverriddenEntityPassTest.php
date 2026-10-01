<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\DependencyInjection;

use Gingerminds\CoreBundle\DependencyInjection\Compiler\OverriddenEntityPass;
use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Entity\User\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class OverriddenEntityPassTest extends TestCase
{
    public function testCollectsEntitiesRegisteredByAnyBundle(): void
    {
        $container = new ContainerBuilder();
        $driver = $container->register('doctrine.orm.default_attribute_metadata_driver', \stdClass::class);

        OverriddenEntityPass::registerOverriddenEntity($container, User::class);
        OverriddenEntityPass::registerOverriddenEntity($container, Role::class);
        // Registered twice (e.g. by two bundles): kept once.
        OverriddenEntityPass::registerOverriddenEntity($container, User::class);

        new OverriddenEntityPass()->process($container);

        self::assertSame([User::class, Role::class], $container->getParameter(OverriddenEntityPass::PARAMETER));
        self::assertSame([], $container->findTaggedServiceIds(OverriddenEntityPass::TAG));
        self::assertSame(
            [['addExcludePaths', [[
                (string) new \ReflectionClass(User::class)->getFileName(),
                (string) new \ReflectionClass(Role::class)->getFileName(),
            ]]]],
            $driver->getMethodCalls(),
        );
    }

    public function testNothingOverridden(): void
    {
        $container = new ContainerBuilder();
        $driver = $container->register('doctrine.orm.default_attribute_metadata_driver', \stdClass::class);

        new OverriddenEntityPass()->process($container);

        self::assertSame([], $container->getParameter(OverriddenEntityPass::PARAMETER));
        self::assertSame([], $driver->getMethodCalls());
    }
}
