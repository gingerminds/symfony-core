<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\ApiPlatform\Filter\ComputedFilterStore;
use Gingerminds\CoreBundle\ApiPlatform\Filter\InjectComputedFiltersListener;
use Gingerminds\CoreBundle\ApiPlatform\Metadata\ContextHeaderParametersResourceMetadataCollectionFactory;
use Gingerminds\CoreBundle\ApiPlatform\Metadata\CoreResourceNameCollectionFactory;
use Gingerminds\CoreBundle\ApiPlatform\Metadata\HeaderParameterRegistry;
use Gingerminds\CoreBundle\ApiPlatform\State\ResourceProcessor;
use Gingerminds\CoreBundle\ApiPlatform\State\ResourceProvider;
use Gingerminds\CoreBundle\ApiPlatform\State\Role\RoleProcessor;
use Gingerminds\CoreBundle\Form\Permission\PermissionType;
use Gingerminds\CoreBundle\Form\Role\RoleType;
use Gingerminds\CoreBundle\Form\User\ContributorType;
use Gingerminds\CoreBundle\Form\User\UserType;
use Symfony\Component\HttpKernel\KernelEvents;

/*
 * API Platform state providers/processors and metadata decorators.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    foreach (
        [
            'user' => [ResourceProcessor::class, UserType::class],
            'contributor' => [ResourceProcessor::class, ContributorType::class],
            'role' => [RoleProcessor::class, RoleType::class],
            'permission' => [ResourceProcessor::class, PermissionType::class],
        ] as $name => [$processorClass, $formType]
    ) {
        $services->set('gingerminds_core.api.provider.' . $name, ResourceProvider::class)
            ->args([service('gingerminds_core.repository.' . $name), service('request_stack')])
            ->tag('api_platform.state_provider');

        $services->set('gingerminds_core.api.processor.' . $name, $processorClass)
            ->args([
                service('gingerminds_core.repository.' . $name),
                service('form.factory'),
                service('request_stack'),
                $formType,
            ])
            ->call('setTranslator', [service('translator')])
            ->tag('api_platform.state_processor');
    }

    $services->set('gingerminds_core.api.metadata.core_resource_names', CoreResourceNameCollectionFactory::class)
        ->decorate('api_platform.metadata.resource.name_collection_factory')
        ->args([
            service('.inner'),
            param('gingerminds_core.overridden_entities'),
        ]);

    $services->set('gingerminds_core.api.header_parameter_registry', HeaderParameterRegistry::class)
        ->args([tagged_iterator('gingerminds_core.api_header_parameter')]);
    $services->alias(HeaderParameterRegistry::class, 'gingerminds_core.api.header_parameter_registry');

    $services->set('gingerminds_core.api.metadata.context_header_parameters', ContextHeaderParametersResourceMetadataCollectionFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_collection_factory', null, 0)
        ->args([
            service('.inner'),
            service('gingerminds_core.api.header_parameter_registry'),
        ]);

    $services->set('gingerminds_core.api.computed_filter_store', ComputedFilterStore::class)
        ->tag('kernel.reset', ['method' => 'reset']);
    $services->alias(ComputedFilterStore::class, 'gingerminds_core.api.computed_filter_store');

    $services->set('gingerminds_core.api.inject_computed_filters_listener', InjectComputedFiltersListener::class)
        ->args([service('gingerminds_core.api.computed_filter_store')])
        ->tag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'priority' => 10]);
};
