<?php

declare(strict_types=1);

use Gingerminds\CoreBundle\Controller\Security\Api\AuthController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The #[Route] of the API controllers, under the API prefix (config/routes.php).
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import(AuthController::class, 'attribute');
};
