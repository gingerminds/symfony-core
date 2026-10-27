<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\Routing;

use Gingerminds\CoreBundle\Controller\AbstractCrudController;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\CoreBundle\Routing\CrudRouteLoader;
use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Gingerminds\CoreBundle\Tests\Application\Entity\Category;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CrudRouteLoaderTest extends TestCase
{
    public function testAnOverrideRestatingItsAttributesKeepsItsRoute(): void
    {
        $routes = $this->loader(RestatingController::class)->load('.', CrudRouteLoader::TYPE);

        self::assertSame('/widgets/new', $routes->get('admin_widget_new')?->getPath());
        self::assertSame(RestatingController::class . '::new', $routes->get('admin_widget_new')->getDefault('_controller'));
        self::assertSame('widget', $routes->get('admin_widget_new')->getDefault('_resource'));
        self::assertSame('/widgets', $routes->get('admin_widget_index')?->getPath());
    }

    public function testAnOverrideDroppingTheAccessCheckIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ForgetfulController::new()" overrides an action declaring #[IsGranted]');

        $this->loader(ForgetfulController::class)->load('.', CrudRouteLoader::TYPE);
    }

    /**
     * @param class-string $controller
     */
    private function loader(string $controller): CrudRouteLoader
    {
        $loader = new CrudRouteLoader(new ResourceRegistry(['widget' => [
            'entity' => Category::class,
            'controller' => $controller,
            'path' => 'widgets',
            'permission' => 'widgets',
            'route_prefix' => 'admin_widget',
            'translation_prefix' => 'widget',
            'translation_domain' => 'messages',
            'template_prefix' => 'admin/widget',
        ]]));
        $loader->setResolver(new LoaderResolver([$loader, new AttributeRouteControllerLoader()]));

        return $loader;
    }
}

final class RestatingController extends AbstractCrudController
{
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted(ResourceVoter::CREATE, subject: new Expression(self::RESOURCE_SUBJECT))]
    public function new(Request $request): Response
    {
        return parent::new($request);
    }
}

final class ForgetfulController extends AbstractCrudController
{
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return parent::new($request);
    }
}
