<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('gingerminds_core_api_login', '/login')
        ->controller('gingerminds_core.controller.api.auth::login')
        ->methods(['POST'])
        ->format('json');

    $routes->add('gingerminds_core_api_logout', '/logout')
        ->controller('gingerminds_core.controller.api.auth::logout')
        ->methods(['POST'])
        ->format('json');
};
