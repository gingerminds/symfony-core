<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Maker\MakeApi;
use Gingerminds\CoreBundle\Maker\MakeCrudController;
use Gingerminds\CoreBundle\Maker\MakeEntity;
use Gingerminds\CoreBundle\Maker\MakeForm;
use Gingerminds\CoreBundle\Maker\MakeRepository;
use Gingerminds\CoreBundle\Maker\MakeResource;
use Gingerminds\CoreBundle\Maker\MakeVoter;
use Gingerminds\CoreBundle\Maker\ResourceGenerator;

/*
 * make:gm:* generators, loaded by GingermindsCoreBundle::loadExtension()
 * only when symfony/maker-bundle is installed.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_core.maker.resource_generator', ResourceGenerator::class);

    $makers = [
        'resource' => MakeResource::class,
        'entity' => MakeEntity::class,
        'repository' => MakeRepository::class,
        'form' => MakeForm::class,
        'crud_controller' => MakeCrudController::class,
        'voter' => MakeVoter::class,
        'api' => MakeApi::class,
    ];

    foreach ($makers as $name => $class) {
        $services->set('gingerminds_core.maker.' . $name, $class)
            ->args([service('gingerminds_core.maker.resource_generator')])
            ->tag('maker.command');
    }
};
