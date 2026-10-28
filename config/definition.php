<?php

declare(strict_types=1);

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

return static function (DefinitionConfigurator $definition): void {
    foreach (['general', 'api', 'resources'] as $file) {
        $definition->import('definition/' . $file . '.php');
    }
};
