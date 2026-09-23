<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Doctrine\EventListener\TimestampableListener;

/*
 * Doctrine listeners (timestamps).
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_core.doctrine.timestampable_listener', TimestampableListener::class)
        ->args([service('clock')])
        ->tag('doctrine.event_listener', ['event' => 'prePersist'])
        ->tag('doctrine.event_listener', ['event' => 'preUpdate']);
};
