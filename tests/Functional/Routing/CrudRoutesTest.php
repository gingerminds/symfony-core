<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Routing;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Controller\User\UserController;
use Gingerminds\CoreBundle\Tests\Application\Controller\CategoryProductController;
use Gingerminds\CoreBundle\Tests\Application\Entity\Category;
use Gingerminds\CoreBundle\Tests\Functional\ApiTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * CRUD routes: #[Route] of AbstractCrudController (and of the subclass) loaded by CrudRouteLoader
 * under the resource path and route prefix, `_resource` default.
 */
final class CrudRoutesTest extends ApiTestCase
{
    public function testCrudRoutesKeepTheirNamesAndPaths(): void
    {
        $routes = self::getContainer()->get(RouterInterface::class)->getRouteCollection();
        $expected = [
            'gingerminds_core_user' => ['/admin/users', 'user', UserController::class],
            'gingerminds_core_role' => ['/admin/roles', 'role', null],
            'gingerminds_core_permission' => ['/admin/permissions', 'permission', null],
            'gingerminds_core_contributor' => ['/admin/contributors', 'contributor', null],
            'admin_category_product' => ['/admin/categories/{categoryId}/products', 'category_product', CategoryProductController::class],
        ];

        foreach ($expected as $prefix => [$path, $resource, $controller]) {
            foreach (['index' => ['', ['GET']], 'new' => ['/new', ['GET', 'POST']], 'edit' => ['/{id}/edit', ['GET', 'POST']], 'delete' => ['/{id}/delete', ['POST']]] as $action => [$suffix, $methods]) {
                $route = $routes->get($prefix . '_' . $action);
                self::assertNotNull($route, $prefix . '_' . $action);
                self::assertSame($path . $suffix, $route->getPath());
                self::assertSame($methods, $route->getMethods());
                self::assertSame($resource, $route->getDefault('_resource'));

                if (null !== $controller) {
                    self::assertSame($controller . '::' . $action, $route->getDefault('_controller'));
                }
            }
        }

        self::assertSame('[^/]+', $routes->get('gingerminds_core_user_edit')?->getRequirement('id'));
    }

    public function testControllerActionsAreRelativeToTheResource(): void
    {
        $route = self::getContainer()->get(RouterInterface::class)->getRouteCollection()->get('admin_category_product_count');

        self::assertNotNull($route);
        self::assertSame('/admin/categories/{categoryId}/products/count', $route->getPath());

        $category = new Category('Drones');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($category);
        $entityManager->flush();
        $this->client->loginUser($this->fixtures->user('router@example.com', ['view products']), 'admin');

        $this->client->request('GET', '/admin/categories/' . $category->getId() . '/products/count');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('category_product', $data['resource']);
        self::assertSame(['categoryId' => (string) $category->getId()], $data['route_parameters']);
    }

    public function testTheRoutingControllersImportLeavesTheCrudControllersToTheCoreLoader(): void
    {
        $routes = self::getContainer()->get(RouterInterface::class)->getRouteCollection();

        foreach (['index', 'new', 'edit', 'delete', 'count'] as $name) {
            self::assertNull($routes->get($name), \sprintf('No unprefixed "%s" route.', $name));
        }

        foreach ($routes->all() as $name => $route) {
            if (str_starts_with((string) $route->getDefault('_controller'), CategoryProductController::class . '::')) {
                self::assertStringStartsWith('admin_category_product_', $name);
            }
        }
    }

    public function testTheRouterCacheIsRebuiltWithTheContainer(): void
    {
        // The registry (a new #[AsCrudController]...) is compiled in the container: the router
        // cache depends on the container file (FrameworkBundle Router), rebuilt with it in debug.
        $container = self::getContainer();
        $containerFile = $container->getParameter('kernel.build_dir') . '/' . $container->getParameter('kernel.container_class') . '.php';
        $resources = array_map('strval', $container->get(RouterInterface::class)->getRouteCollection()->getResources());

        self::assertContains(realpath($containerFile), $resources);
    }
}
