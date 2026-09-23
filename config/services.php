<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    foreach (['resource', 'filter', 'repository', 'form', 'security', 'api_platform', 'cache', 'doctrine', 'controller', 'command'] as $file) {
        $container->import('services/' . $file . '.php');
    }
};
