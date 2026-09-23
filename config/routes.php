<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Import from the project:
 *
 *     # config/routes/gingerminds_core.yaml
 *     gingerminds_core:
 *         resource: '@GingermindsCoreBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('routes/admin.php')->prefix('/%gingerminds_core.admin_prefix%');
    $routes->import('.', 'gingerminds_crud')->prefix('/%gingerminds_core.admin_prefix%');
    $routes->import('routes/api.php')->prefix('/%gingerminds_core.api.prefix%');

    $routes->add('gingerminds_core_health', '/%gingerminds_core.health_check_path%')
        ->controller('gingerminds_core.controller.health')
        ->methods(['GET']);
};
