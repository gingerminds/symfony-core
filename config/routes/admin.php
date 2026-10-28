<?php

declare(strict_types=1);

use Gingerminds\CoreBundle\Controller\Dashboard\DashboardController;
use Gingerminds\CoreBundle\Controller\Security\LoginController;
use Gingerminds\CoreBundle\Controller\Security\LogoutController;
use Gingerminds\CoreBundle\Controller\User\ProfileController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The #[Route] of the admin controllers, under the admin prefix (config/routes.php).
 */
return static function (RoutingConfigurator $routes): void {
    foreach ([DashboardController::class, LoginController::class, LogoutController::class, ProfileController::class] as $controller) {
        $routes->import($controller, 'attribute');
    }

    // UX Autocomplete endpoint, behind the admin firewall: the autocompleter of each resource
    // (ResourceAutocompleter, aliased by the resource name) and the project's own.
    $routes->add('gingerminds_core_autocomplete', '/_autocomplete/{alias}')
        ->controller('ux.autocomplete.autocomplete_controller')
        ->requirements(['alias' => '[A-Za-z0-9_-]+'])
        ->methods(['GET']);
};
