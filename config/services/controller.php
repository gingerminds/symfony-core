<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Controller\Dashboard\DashboardController;
use Gingerminds\CoreBundle\Controller\EventListener\InvalidCsrfTokenListener;
use Gingerminds\CoreBundle\Controller\EventListener\ResourceEntityRouteListener;
use Gingerminds\CoreBundle\Controller\HealthController;
use Gingerminds\CoreBundle\Controller\Permission\PermissionController;
use Gingerminds\CoreBundle\Controller\Role\RoleController;
use Gingerminds\CoreBundle\Controller\Security\Api\AuthController;
use Gingerminds\CoreBundle\Controller\Security\LoginController;
use Gingerminds\CoreBundle\Controller\Security\LogoutController;
use Gingerminds\CoreBundle\Controller\User\ContributorController;
use Gingerminds\CoreBundle\Controller\User\ProfileController;
use Gingerminds\CoreBundle\Controller\User\UserController;
use Gingerminds\CoreBundle\Controller\ValueResolver\ResourceEntityValueResolver;
use Gingerminds\CoreBundle\Repository\Security\ApiTokenRepository;
use Gingerminds\CoreBundle\Repository\User\UserRepository;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/*
 * Admin, security and API controllers.
 *
 * They extend AbstractController: a service subscriber (`container.service_subscriber`, its
 * helpers' services in a locator injected by setContainer()), public, with action arguments
 * (`controller.service_arguments`). The project's own controllers get the same by autoconfiguration.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $controller = static fn (string $id, string $class): ServiceConfigurator => $services->set($id, $class)
        ->public()
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->call('setContainer', [service(ContainerInterface::class)]);

    foreach (
        [
            'user' => UserController::class,
            'contributor' => ContributorController::class,
            'role' => RoleController::class,
            'permission' => PermissionController::class,
        ] as $name => $class
    ) {
        $controller('gingerminds_core.controller.admin.' . $name, $class);
        $services->alias($class, 'gingerminds_core.controller.admin.' . $name)->public();
    }

    $services->set('gingerminds_core.value_resolver.resource_entity', ResourceEntityValueResolver::class)
        ->args([service('gingerminds_core.resource_registry'), service('doctrine')])
        ->tag('controller.targeted_value_resolver', ['name' => ResourceEntityValueResolver::class]);

    // Before the #[IsGranted] / #[IsCsrfTokenValid] checks: an entity of another parent is a 404.
    $services->set('gingerminds_core.listener.resource_entity_route', ResourceEntityRouteListener::class)
        ->tag('kernel.event_listener', ['event' => KernelEvents::CONTROLLER_ARGUMENTS, 'priority' => ResourceEntityRouteListener::PRIORITY]);

    // Before the firewall's ExceptionListener (priority 1), which sends to the login page.
    $services->set('gingerminds_core.listener.invalid_csrf_token', InvalidCsrfTokenListener::class)
        ->args([service('gingerminds_core.resource_registry'), service('router'), service('translator')])
        ->tag('kernel.event_listener', ['event' => KernelEvents::EXCEPTION, 'priority' => InvalidCsrfTokenListener::PRIORITY]);

    $controller('gingerminds_core.controller.admin.dashboard', DashboardController::class);
    $controller('gingerminds_core.controller.admin.profile', ProfileController::class)
        ->args([service('doctrine'), service('translator')]);
    $controller('gingerminds_core.controller.security.login', LoginController::class)
        ->args([service('security.authentication_utils')]);
    $controller('gingerminds_core.controller.security.logout', LogoutController::class);
    $controller('gingerminds_core.controller.health', HealthController::class);
    $controller('gingerminds_core.controller.api.auth', AuthController::class)
        ->args([
            service(UserRepository::class),
            service(ApiTokenRepository::class),
            service('security.user_password_hasher'),
            service('limiter.gingerminds_core_api_login'),
            tagged_iterator('gingerminds_core.login_response_enricher'),
            param('gingerminds_core.api.token_ttl'),
            service('translator'),
        ]);

    // The #[Route] attributes name the controller class: `_controller: Class` / `Class::action`.
    foreach (
        [
            'admin.dashboard' => DashboardController::class,
            'admin.profile' => ProfileController::class,
            'security.login' => LoginController::class,
            'security.logout' => LogoutController::class,
            'health' => HealthController::class,
            'api.auth' => AuthController::class,
        ] as $id => $class
    ) {
        $services->alias($class, 'gingerminds_core.controller.' . $id)->public();
    }
};
