<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Command\Permission\SyncPermissionsCommand;
use Gingerminds\CoreBundle\Command\User\CreateUserCommand;

/*
 * Console commands.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_core.command.sync_permissions', SyncPermissionsCommand::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('gingerminds_core.resource_registry'),
            param('gingerminds_core.permissions'),
        ])
        ->tag('console.command');
    $services->set('gingerminds_core.command.create_user', CreateUserCommand::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('gingerminds_core.resource_registry'),
            service('security.user_password_hasher'),
            service('validator'),
        ])
        ->tag('console.command');
};
