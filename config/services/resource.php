<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Menu\AdminMenu;
use Gingerminds\CoreBundle\Menu\CoreAdminMenuProvider;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\CoreBundle\Routing\CrudRouteLoader;
use Gingerminds\CoreBundle\Twig\AdminExtension;
use Gingerminds\CoreBundle\Twig\ListExtension;
use Gingerminds\CoreBundle\Twig\ResourceExtension;

/*
 * Resource registry, CRUD route loader, admin menu and Twig functions.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_core.resource_registry', ResourceRegistry::class)
        ->args(['$resources' => []]); // filled by ResourceRegistryPass
    $services->alias(ResourceRegistry::class, 'gingerminds_core.resource_registry');
    $services->set('gingerminds_core.routing.crud_loader', CrudRouteLoader::class)
        ->args([service('gingerminds_core.resource_registry'), param('kernel.environment')])
        ->tag('routing.loader');
    $services->set('gingerminds_core.admin_menu', AdminMenu::class)
        ->args([
            tagged_iterator('gingerminds_core.admin_menu_provider'),
            service('security.authorization_checker'),
        ]);
    $services->alias(AdminMenu::class, 'gingerminds_core.admin_menu');

    $services->set('gingerminds_core.admin_menu.core_provider', CoreAdminMenuProvider::class)
        ->args([service('gingerminds_core.resource_registry')])
        ->tag('gingerminds_core.admin_menu_provider');

    // #[AsTwigFunction] methods (autoconfigured): Twig runtimes, built on their first call.
    $services->set('gingerminds_core.twig.resource', ResourceExtension::class)
        ->args([service('gingerminds_core.resource_registry'), service('doctrine')])
        ->autoconfigure();
    $services->set('gingerminds_core.twig.admin', AdminExtension::class)
        ->args([
            service('gingerminds_core.admin_menu'),
            service('request_stack'),
            service('translator'),
            param('gingerminds_core.admin_title'),
            param('gingerminds_core.admin_title_translation_domain'),
            param('gingerminds_core.admin_includes'),
        ])
        ->autoconfigure();
    $services->set('gingerminds_core.twig.list', ListExtension::class)
        ->args([service('router')])
        ->autoconfigure();
};
