<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\DependencyInjection;

use Gingerminds\CoreBundle\Controller\User\UserController;
use Gingerminds\CoreBundle\DependencyInjection\Compiler\ResourceRegistryPass;
use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class ResourceRegistryPassTest extends TestCase
{
    public function testMergesCoreAttributeAndConfigurationResources(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('gingerminds_core.resource_registry', new Definition(ResourceRegistry::class, ['$resources' => []]));
        $container->setParameter('gingerminds_core.resources_config', [
            'role' => ['entity' => 'App\Entity\Role', 'controller' => null],
            'product_category' => ['entity' => 'App\Entity\ProductCategory'],
        ]);
        $container->register('App\Controller\Catalog\ProductCategoryController')
            ->addTag(ResourceRegistryPass::CRUD_CONTROLLER_TAG, ['resource' => 'product_category', 'entity' => 'App\Entity\Old', 'form' => 'App\Form\ProductCategoryType']);

        new ResourceRegistryPass()->process($container);

        $resources = $container->getDefinition('gingerminds_core.resource_registry')->getArgument('$resources');

        // Core defaults
        self::assertSame(UserController::class, $resources['user']['controller']);
        self::assertSame('gingerminds_core_user', $resources['user']['route_prefix']);
        self::assertSame('@GingermindsCore/pages/user', $resources['user']['template_prefix']);

        // Configuration overrides the core entity only (null values ignored)
        self::assertSame('App\Entity\Role', $resources['role']['entity']);
        self::assertNotSame(Role::class, $resources['role']['entity']);
        self::assertNotNull($resources['role']['controller']);

        // Attribute + configuration + conventional defaults
        self::assertSame('App\Entity\ProductCategory', $resources['product_category']['entity']);
        self::assertSame('App\Controller\Catalog\ProductCategoryController', $resources['product_category']['controller']);
        self::assertSame('App\Form\ProductCategoryType', $resources['product_category']['form']);
        self::assertSame('product-categories', $resources['product_category']['path']);
        self::assertSame('product_categories', $resources['product_category']['permission']);
        self::assertSame('admin_product_category', $resources['product_category']['route_prefix']);
        self::assertSame('admin/product_category', $resources['product_category']['template_prefix']);
        self::assertSame('messages', $resources['product_category']['translation_domain']);
    }
}
