<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Routing;

use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Generates the CRUD routes of every registered resource (`type: gingerminds_crud`):
 *
 * | Route             | Path                   | Methods   |
 * |-------------------|------------------------|-----------|
 * | {prefix}_index    | /{path}                | GET       |
 * | {prefix}_new      | /{path}/new            | GET, POST |
 * | {prefix}_edit     | /{path}/{id}/edit      | GET, POST |
 * | {prefix}_delete   | /{path}/{id}/delete    | POST      |
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

            $path = '/' . trim($definition->path, '/');
            $controller = $definition->controller;

            $routes->add($definition->route('index'), new Route($path, ['_controller' => $controller . '::index'], methods: ['GET']));
            $routes->add($definition->route('new'), new Route($path . '/new', ['_controller' => $controller . '::new'], methods: ['GET', 'POST']));
            $routes->add($definition->route('edit'), new Route($path . '/{id}/edit', ['_controller' => $controller . '::edit'], ['id' => '[^/]+'], methods: ['GET', 'POST']));
            $routes->add($definition->route('delete'), new Route($path . '/{id}/delete', ['_controller' => $controller . '::delete'], ['id' => '[^/]+'], methods: ['POST']));
        }

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return self::TYPE === $type;
    }
}
