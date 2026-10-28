<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\DependencyInjection;

use Gingerminds\CoreBundle\Controller\Role\RoleController;
use Gingerminds\CoreBundle\Controller\User\UserController;
use Gingerminds\CoreBundle\DependencyInjection\Compiler\ResourceRegistryPass;
use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Entity\User\User;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class ResourceRegistryPassTest extends TestCase
{
    public function testMergesBundleAttributeAndConfigurationResources(): void
    {
        $container = $this->container();
        $container->setParameter('gingerminds_core.resources_config', [
            'role' => ['entity' => 'App\Entity\Role', 'controller' => null],
            'product_category' => ['entity' => 'App\Entity\ProductCategory'],
        ]);
        $container->register('App\Controller\Catalog\ProductCategoryController')
            ->addTag(ResourceRegistryPass::CRUD_CONTROLLER_TAG, ['resource' => 'product_category', 'entity' => 'App\Entity\Old', 'form' => 'App\Form\ProductCategoryType']);

        new ResourceRegistryPass()->process($container);

        $resources = $container->getDefinition('gingerminds_core.resource_registry')->getArgument('$resources');

        // Bundle resources (AbstractResourceBundle)
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

        // Redirect after save: core defaults when no global configuration
        self::assertSame('index', $resources['user']['redirect_after_new']);
        self::assertSame('edit', $resources['user']['redirect_after_edit']);

        // The placeholders are removed.
        self::assertSame([], $container->findTaggedServiceIds(ResourceRegistryPass::BUNDLE_RESOURCE_TAG));
    }

    public function testAnAttributeOverridesABundleResource(): void
    {
        $container = $this->container();
        $container->setParameter('gingerminds_core.resources_config', []);
        $container->register('App\Controller\UserController')
            ->addTag(ResourceRegistryPass::CRUD_CONTROLLER_TAG, ['resource' => 'user', 'template_prefix' => 'admin/user']);

        new ResourceRegistryPass()->process($container);

        $user = $container->getDefinition('gingerminds_core.resource_registry')->getArgument('$resources')['user'];
        self::assertSame('App\Controller\UserController', $user['controller']);
        self::assertSame('admin/user', $user['template_prefix']);
        self::assertSame(User::class, $user['entity']);
    }

    public function testCrudControllersAreLeftToTheCrudRouteLoader(): void
    {
        $container = $this->container();
        $container->setParameter('gingerminds_core.resources_config', ['user' => ['controller' => 'App\Controller\UserController']]);
        $container->register('App\Controller\ProductController')
            ->addTag(ResourceRegistryPass::CRUD_CONTROLLER_TAG, ['resource' => 'product', 'entity' => 'App\Entity\Product'])
            ->addTag('routing.controller');
        $container->register('app.user_controller', 'App\Controller\UserController')->addTag('routing.controller');
        $container->register('App\Controller\HomeController')->addTag('routing.controller');

        new ResourceRegistryPass()->process($container);

        self::assertFalse($container->getDefinition('App\Controller\ProductController')->hasTag('routing.controller'), 'Bound by #[AsCrudController].');
        self::assertFalse($container->getDefinition('app.user_controller')->hasTag('routing.controller'), 'Controller class of a configured resource.');
        self::assertTrue($container->getDefinition('App\Controller\HomeController')->hasTag('routing.controller'));
    }

    public function testRedirectAfterSaveResolution(): void
    {
        $container = $this->container();
        $container->setParameter('gingerminds_core.redirect_after_save', ['new' => 'edit', 'edit' => 'index']);
        $container->setParameter('gingerminds_core.resources_config', [
            'page' => ['entity' => 'App\Entity\Page', 'redirect_after_edit' => 'edit'],
        ]);
        $container->register('App\Controller\PageController')
            ->addTag(ResourceRegistryPass::CRUD_CONTROLLER_TAG, ['resource' => 'page', 'entity' => 'App\Entity\Page', 'redirect_after_new' => 'index', 'redirect_after_edit' => 'index']);

        new ResourceRegistryPass()->process($container);

        $resources = $container->getDefinition('gingerminds_core.resource_registry')->getArgument('$resources');

        // Global configuration
        self::assertSame('edit', $resources['user']['redirect_after_new']);
        self::assertSame('index', $resources['user']['redirect_after_edit']);

        // Attribute overrides the global value, configuration overrides the attribute
        self::assertSame('index', $resources['page']['redirect_after_new']);
        self::assertSame('edit', $resources['page']['redirect_after_edit']);
    }

    /**
     * The registry and the core resources, as GingermindsCoreBundle registers them.
     */
    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('gingerminds_core.resource_registry', new Definition(ResourceRegistry::class, ['$resources' => []]));

        foreach (['user' => [User::class, UserController::class, 'users'], 'role' => [Role::class, RoleController::class, 'roles']] as $name => [$entity, $controller, $path]) {
            ResourceRegistryPass::registerBundleResource($container, $name, [
                'entity' => $entity,
                'controller' => $controller,
                'path' => $path,
                'permission' => $path,
                'route_prefix' => 'gingerminds_core_' . $name,
                'translation_prefix' => $name,
                'translation_domain' => 'GingermindsCore',
                'template_prefix' => '@GingermindsCore/pages/' . $name,
            ]);
        }

        return $container;
    }
}
