<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Menu\AdminMenu;
use Gingerminds\CoreBundle\Menu\CoreAdminMenuProvider;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\CoreBundle\Routing\CrudRouteLoader;
use Gingerminds\CoreBundle\Twig\GingermindsCoreExtension;

/*
 * Resource registry, CRUD route loader, admin menu and Twig extension.
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

    $services->set('gingerminds_core.twig.extension', GingermindsCoreExtension::class)
        ->args([
            service('gingerminds_core.resource_registry'),
            service('gingerminds_core.admin_menu'),
            service('router'),
            service('request_stack'),
            service('doctrine'),
            service('translator'),
            param('gingerminds_core.admin_title'),
            param('gingerminds_core.admin_title_translation_domain'),
        ])
        ->tag('twig.extension');
};
