<?php

declare(strict_types=1);

use Gingerminds\CoreBundle\Controller\AutocompleteController;
use Gingerminds\CoreBundle\Controller\Dashboard\DashboardController;
use Gingerminds\CoreBundle\Controller\Security\LoginController;
use Gingerminds\CoreBundle\Controller\Security\LogoutController;
use Gingerminds\CoreBundle\Controller\User\ProfileController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The #[Route] of the admin controllers, under the admin prefix (config/routes.php).
 */
return static function (RoutingConfigurator $routes): void {
    foreach ([DashboardController::class, LoginController::class, LogoutController::class, ProfileController::class, AutocompleteController::class] as $controller) {
        $routes->import($controller, 'attribute');
    }
};
