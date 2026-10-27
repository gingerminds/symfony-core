<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Doctrine\EventListener\TimestampableListener;
use Gingerminds\CoreBundle\Doctrine\EventListener\UserPasswordListener;
use Gingerminds\CoreBundle\Service\DisplayTimezone;

/*
 * Doctrine listeners (timestamps, user password hashing) and the display timezone (dates are stored in UTC, see the `datetime` types).
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_core.doctrine.timestampable_listener', TimestampableListener::class)
        ->args([service('clock')])
        ->tag('doctrine.event_listener', ['event' => 'prePersist'])
        ->tag('doctrine.event_listener', ['event' => 'preUpdate']);

    $services->set('gingerminds_core.doctrine.user_password_listener', UserPasswordListener::class)
        ->args([service('security.user_password_hasher')])
        ->tag('doctrine.orm.entity_listener', ['entity' => '%gingerminds_core.resource.user.entity%', 'event' => 'preFlush']);

    $services->set('gingerminds_core.display_timezone', DisplayTimezone::class)
        ->args([param('gingerminds_core.display_timezone')]);
    $services->alias(DisplayTimezone::class, 'gingerminds_core.display_timezone');
};
