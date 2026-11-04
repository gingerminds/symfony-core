<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Command\Permission\SyncPermissionsCommand;
use Gingerminds\CoreBundle\Command\Security\PurgeApiTokensCommand;
use Gingerminds\CoreBundle\Command\User\CreateUserCommand;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

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
            service('validator'),
        ])
        ->tag('console.command');

    $purgeTokens = $services->set('gingerminds_core.command.purge_api_tokens', PurgeApiTokensCommand::class)
        ->args([service('gingerminds_core.repository.api_token'), service('clock')])
        ->tag('console.command');

    // A daily task of the `default` schedule with symfony/scheduler (the tag of #[AsPeriodicTask],
    // an attribute that would break the autoconfiguration without the component).
    if (class_exists(AsPeriodicTask::class)) {
        $purgeTokens->tag('scheduler.task', [...get_object_vars(new AsPeriodicTask(frequency: '1 day')), 'trigger' => 'every']);
    }
};
