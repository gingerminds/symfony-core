<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Repository\Permission\PermissionRepository;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;
use Gingerminds\CoreBundle\Repository\Role\RoleRepository;
use Gingerminds\CoreBundle\Repository\Security\ApiTokenRepository;
use Gingerminds\CoreBundle\Repository\Security\ApiTokenRepositoryInterface;
use Gingerminds\CoreBundle\Repository\User\ContributorRepository;
use Gingerminds\CoreBundle\Repository\User\ContributorRepositoryInterface;
use Gingerminds\CoreBundle\Repository\User\UserRepository;
use Gingerminds\CoreBundle\Repository\User\UserRepositoryInterface;

/*
 * Repositories. Doctrine requires the FQCN as service id for the bundle ones.
 * `gingerminds_core.repository.*` (and the interfaces) are the repository Doctrine gives for the
 * configured entity: the `repositoryClass` of a project entity, used everywhere.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    foreach (
        [
            'user' => [UserRepository::class, UserRepositoryInterface::class],
            'contributor' => [ContributorRepository::class, ContributorRepositoryInterface::class],
            'role' => [RoleRepository::class, null],
            'permission' => [PermissionRepository::class, null],
        ] as $name => [$class, $interface]
    ) {
        $services->set($class)
            ->args([service('doctrine'), param('gingerminds_core.resource.' . $name . '.entity')])
            ->tag('doctrine.repository_service');
        $services->set('gingerminds_core.repository.' . $name, $interface ?? RepositoryInterface::class)
            ->factory([service('doctrine'), 'getRepository'])
            ->args([param('gingerminds_core.resource.' . $name . '.entity')]);

        if (null !== $interface) {
            $services->alias($interface, 'gingerminds_core.repository.' . $name);
        }
    }

    $services->set(ApiTokenRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');
    $services->alias('gingerminds_core.repository.api_token', ApiTokenRepository::class);
    $services->alias(ApiTokenRepositoryInterface::class, 'gingerminds_core.repository.api_token');
};
