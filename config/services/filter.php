<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Repository\Filter\FilterHandlerRegistry;
use Gingerminds\CoreBundle\Repository\Filter\Handler\BooleanFilterHandler;
use Gingerminds\CoreBundle\Repository\Filter\Handler\DateFilterHandler;
use Gingerminds\CoreBundle\Repository\Filter\Handler\NumberFilterHandler;
use Gingerminds\CoreBundle\Repository\Filter\Handler\SelectEntityFilterHandler;
use Gingerminds\CoreBundle\Repository\Filter\Handler\SelectEnumFilterHandler;
use Gingerminds\CoreBundle\Repository\Filter\Handler\SelectFilterHandler;

/*
 * List filter handlers (`gingerminds_core.filter_handler` tag) and their registry.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $filterHandlers = [
        'boolean' => BooleanFilterHandler::class,
        'date' => DateFilterHandler::class,
        'number' => NumberFilterHandler::class,
        'select' => SelectFilterHandler::class,
        'select_entity' => SelectEntityFilterHandler::class,
        'select_enum' => SelectEnumFilterHandler::class,
    ];

    foreach ($filterHandlers as $name => $class) {
        $services->set('gingerminds_core.filter_handler.' . $name, $class);
    }

    $services->get('gingerminds_core.filter_handler.boolean')->tag('gingerminds_core.filter_handler', ['type' => 'boolean']);
    $services->get('gingerminds_core.filter_handler.date')->tag('gingerminds_core.filter_handler', ['type' => 'date']);
    $services->get('gingerminds_core.filter_handler.number')->tag('gingerminds_core.filter_handler', ['type' => 'number']);
    $services->get('gingerminds_core.filter_handler.select')->tag('gingerminds_core.filter_handler', ['type' => 'select']);
    $services->get('gingerminds_core.filter_handler.select_entity')
        ->tag('gingerminds_core.filter_handler', ['type' => 'select-entity'])
        ->tag('gingerminds_core.filter_handler', ['type' => 'select-model']);
    $services->get('gingerminds_core.filter_handler.select_enum')
        ->tag('gingerminds_core.filter_handler', ['type' => 'select-enum'])
        ->tag('gingerminds_core.filter_handler', ['type' => 'select-state']);

    $services->set('gingerminds_core.filter_handler_registry', FilterHandlerRegistry::class)
        ->args([tagged_locator('gingerminds_core.filter_handler', 'type')]);
    $services->alias(FilterHandlerRegistry::class, 'gingerminds_core.filter_handler_registry');
};
