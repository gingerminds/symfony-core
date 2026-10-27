<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Routing;

use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The routes of the resource controllers (`type: gingerminds_crud`): for each resource bound to
 * a controller, the #[Route] attributes of the controller (AbstractCrudController's actions and
 * the subclass' own), relative to the resource:
 *
 * | Route             | Path                   | Methods   |
 * |-------------------|------------------------|-----------|
 * | {prefix}_index    | /{path}                | GET       |
 * | {prefix}_new      | /{path}/new            | GET, POST |
 * | {prefix}_edit     | /{path}/{id}/edit      | GET, POST |
 * | {prefix}_delete   | /{path}/{id}/delete    | POST      |
 *
 * `{path}`, `{prefix}` and the controller come from the resource registry rather than from a
 * class-level #[Route]: the core and bundle resources are configurable by the project
 * (`resources.*.path|route_prefix|controller`) and one controller class may serve several
 * resources. Each route gets the `_resource` default (AbstractCrudController::getResource()).
 * ResourceRegistryPass keeps these controllers out of the `routing.controllers` import.
 *
 * The registry comes from the container: in debug, the router cache is rebuilt with it (the
 * FrameworkBundle router tracks the compiled container file), e.g. for a new #[AsCrudController].
 */
final class CrudRouteLoader extends Loader
{
    public const string TYPE = 'gingerminds_crud';

    public function __construct(
        private readonly ResourceRegistry $resources,
        ?string $env = null,
    ) {
        parent::__construct($env);
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();

        foreach ($this->resources->all() as $definition) {
            if (null === $definition->controller) {
                continue;
            }

            if (!class_exists($definition->controller)) {
                throw new \LogicException(\sprintf('The controller of the resource "%s" must be a class name, "%s" given.', $definition->name, $definition->controller));
            }

            $this->assertOverridesRestateTheirAttributes($definition->controller);
            $controllerRoutes = $this->import($definition->controller, 'attribute');

            if (!$controllerRoutes instanceof RouteCollection) {
                continue;
            }

            $path = '/' . trim($definition->path, '/');

            // Routes and resources only: the `Controller::action` aliases would be ambiguous
            // for a controller class serving several resources.
            foreach ($controllerRoutes->all() as $name => $route) {
                // `#[Route('')]` is the resource path itself, without trailing slash.
                $route->setPath('/' === $route->getPath() ? $path : $path . $route->getPath());
                $route->addDefaults(['_resource' => $definition->name]);
                $routes->add($definition->routePrefix . '_' . $name, $route);
            }

            foreach ($controllerRoutes->getResources() as $controllerResource) {
                $routes->addResource($controllerResource);
            }
        }

        return $routes;
    }

    /**
     * PHP does not inherit attributes: an action overriding a routed one (AbstractCrudController::new()
     * overridden to add template variables) would silently lose its route and, worse, its access
     * check, still evaluated on the called method only. Such an override must restate them.
     *
     * @param class-string $controller
     */
    private function assertOverridesRestateTheirAttributes(string $controller): void
    {
        foreach (new \ReflectionClass($controller)->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $parentClass = $method->getDeclaringClass()->getParentClass();

            if (false === $parentClass || !$parentClass->hasMethod($method->getName())) {
                continue;
            }

            $overridden = $parentClass->getMethod($method->getName());

            foreach ([Route::class, IsGranted::class, IsCsrfTokenValid::class] as $attribute) {
                if ($this->declares($overridden, $attribute) && !$this->declares($method, $attribute)) {
                    throw new \LogicException(\sprintf(
                        '"%s::%s()" overrides an action declaring #[%s]: restate it (attributes are not inherited).',
                        $method->getDeclaringClass()->getName(),
                        $method->getName(),
                        substr(strrchr($attribute, '\\') ?: $attribute, 1),
                    ));
                }
            }
        }
    }

    /**
     * @param class-string $attribute
     */
    private function declares(\ReflectionMethod $method, string $attribute): bool
    {
        return [] !== $method->getAttributes($attribute, \ReflectionAttribute::IS_INSTANCEOF);
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return self::TYPE === $type;
    }
}
