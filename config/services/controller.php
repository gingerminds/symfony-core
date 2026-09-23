<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Controller\AutocompleteController;
use Gingerminds\CoreBundle\Controller\CrudContext;
use Gingerminds\CoreBundle\Controller\Dashboard\DashboardController;
use Gingerminds\CoreBundle\Controller\HealthController;
use Gingerminds\CoreBundle\Controller\Permission\PermissionController;
use Gingerminds\CoreBundle\Controller\Role\RoleController;
use Gingerminds\CoreBundle\Controller\Security\Api\AuthController;
use Gingerminds\CoreBundle\Controller\Security\LoginController;
use Gingerminds\CoreBundle\Controller\Security\LogoutController;
use Gingerminds\CoreBundle\Controller\User\ContributorController;
use Gingerminds\CoreBundle\Controller\User\ProfileController;
use Gingerminds\CoreBundle\Controller\User\UserController;
use Gingerminds\CoreBundle\Repository\Security\ApiTokenRepository;
use Gingerminds\CoreBundle\Repository\User\UserRepository;

/*
 * Admin, security and API controllers.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_core.controller.context', CrudContext::class)
        ->args([
            service('twig'),
            service('form.factory'),
            service('router'),
            service('security.authorization_checker'),
            service('security.csrf.token_manager'),
            service('translator'),
            service('gingerminds_core.resource_registry'),
            service('doctrine'),
        ]);
    $services->alias(CrudContext::class, 'gingerminds_core.controller.context');

    foreach (
        [
            'user' => UserController::class,
            'contributor' => ContributorController::class,
            'role' => RoleController::class,
            'permission' => PermissionController::class,
        ] as $name => $class
    ) {
        $services->set('gingerminds_core.controller.admin.' . $name, $class)
            ->args([service('gingerminds_core.controller.context')])
            ->tag('controller.service_arguments');
        $services->alias($class, 'gingerminds_core.controller.admin.' . $name)->public();
    }

    $services->set('gingerminds_core.controller.admin.dashboard', DashboardController::class)
        ->args([service('twig')])
        ->tag('controller.service_arguments')
        ->public();
    $services->set('gingerminds_core.controller.admin.profile', ProfileController::class)
        ->args([service('gingerminds_core.controller.context'), service('security.helper')])
        ->tag('controller.service_arguments')
        ->public();
    $services->set('gingerminds_core.controller.admin.autocomplete', AutocompleteController::class)
        ->args([service('gingerminds_core.controller.context')])
        ->tag('controller.service_arguments')
        ->public();
    $services->set('gingerminds_core.controller.security.login', LoginController::class)
        ->args([service('twig'), service('security.authentication_utils')])
        ->tag('controller.service_arguments')
        ->public();
    $services->set('gingerminds_core.controller.security.logout', LogoutController::class)
        ->tag('controller.service_arguments')
        ->public();
    $services->set('gingerminds_core.controller.health', HealthController::class)
        ->tag('controller.service_arguments')
        ->public();
    $services->set('gingerminds_core.controller.api.auth', AuthController::class)
        ->args([
            service(UserRepository::class),
            service(ApiTokenRepository::class),
            service('security.user_password_hasher'),
            service('limiter.gingerminds_core_api_login'),
            service('security.helper'),
            tagged_iterator('gingerminds_core.login_response_enricher'),
            param('gingerminds_core.api.token_ttl'),
            service('translator'),
        ])
        ->tag('controller.service_arguments')
        ->public();
};
