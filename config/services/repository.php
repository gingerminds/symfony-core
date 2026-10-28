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

    foreach (
        [
            'user' => UserRepository::class,
            'contributor' => ContributorRepository::class,
            'role' => RoleRepository::class,
            'permission' => PermissionRepository::class,
        ] as $name => $class
    ) {
        $services->set($class)
            ->args([service('doctrine'), param('gingerminds_core.resource.' . $name . '.entity')])
            ->call('setParameterExtension', [service('api_platform.doctrine.orm.extension.parameter_extension')])
            ->tag('doctrine.repository_service');
        $services->alias('gingerminds_core.repository.' . $name, $class);
    }

    $services->set(ApiTokenRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');
    $services->alias('gingerminds_core.repository.api_token', ApiTokenRepository::class);
};
