<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Repository\Permission\PermissionRepository;
use Gingerminds\CoreBundle\Repository\Role\RoleRepository;
use Gingerminds\CoreBundle\Repository\Security\ApiTokenRepository;
use Gingerminds\CoreBundle\Repository\User\ContributorRepository;
use Gingerminds\CoreBundle\Repository\User\UserRepository;

/*
 * Repositories. Doctrine requires the FQCN as service id: `gingerminds_core.repository.*` are aliases.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(UserRepository::class)
        ->args([
            service('doctrine'),
            param('gingerminds_core.resource.user.entity'),
            service('security.user_password_hasher'),
            param('gingerminds_core.resource.contributor.entity'),
        ])
        ->call('setFilterHandlerRegistry', [service('gingerminds_core.filter_handler_registry')])
        ->tag('doctrine.repository_service');
    $services->alias('gingerminds_core.repository.user', UserRepository::class);

    foreach (
        [
            'contributor' => ContributorRepository::class,
            'role' => RoleRepository::class,
            'permission' => PermissionRepository::class,
        ] as $name => $class
    ) {
        $services->set($class)
            ->args([service('doctrine'), param('gingerminds_core.resource.' . $name . '.entity')])
            ->call('setFilterHandlerRegistry', [service('gingerminds_core.filter_handler_registry')])
            ->tag('doctrine.repository_service');
        $services->alias('gingerminds_core.repository.' . $name, $class);
    }

    $services->set(ApiTokenRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');
    $services->alias('gingerminds_core.repository.api_token', ApiTokenRepository::class);
};
