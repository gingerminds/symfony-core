<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('gingerminds_core_dashboard', '/')
        ->controller('gingerminds_core.controller.admin.dashboard')
        ->methods(['GET']);

    $routes->add('gingerminds_core_login', '/login')
        ->controller('gingerminds_core.controller.security.login')
        ->methods(['GET', 'POST']);

    $routes->add('gingerminds_core_logout', '/logout')
        ->controller('gingerminds_core.controller.security.logout')
        ->methods(['GET', 'POST']);

    $routes->add('gingerminds_core_profile', '/profile')
        ->controller('gingerminds_core.controller.admin.profile')
        ->methods(['GET', 'POST']);

    $routes->add('gingerminds_core_autocomplete', '/_autocomplete/{resource}')
        ->controller('gingerminds_core.controller.admin.autocomplete')
        ->requirements(['resource' => '[a-z0-9_]+'])
        ->methods(['GET']);
};
