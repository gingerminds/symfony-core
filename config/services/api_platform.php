<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\ApiPlatform\Filter\ComputedFilterStore;
use Gingerminds\CoreBundle\ApiPlatform\Filter\InjectComputedFiltersListener;
use Gingerminds\CoreBundle\ApiPlatform\Metadata\ContextHeaderParametersResourceMetadataCollectionFactory;
use Gingerminds\CoreBundle\ApiPlatform\Metadata\CoreResourceNameCollectionFactory;
use Gingerminds\CoreBundle\ApiPlatform\Metadata\HeaderParameterRegistry;
use Gingerminds\CoreBundle\ApiPlatform\Serializer\RelationIdDenormalizer;
use Gingerminds\CoreBundle\ApiPlatform\State\RepositoryProcessor;
use Gingerminds\CoreBundle\ApiPlatform\State\ResourceProvider;
use Symfony\Component\HttpKernel\KernelEvents;

/*
 * API Platform state providers, write processors, relation denormalizer and metadata decorators.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    foreach (['user', 'contributor', 'role', 'permission'] as $name) {
        $services->set('gingerminds_core.api.provider.' . $name, ResourceProvider::class)
            ->args([service('gingerminds_core.repository.' . $name), service('request_stack')])
            ->tag('api_platform.state_provider');
    }

    // Writes: the native Doctrine processors, run inside the repository save / remove hooks.
    foreach (['persist', 'remove'] as $write) {
        $services->set('gingerminds_core.api.processor.' . $write, RepositoryProcessor::class)
            ->decorate('api_platform.doctrine.orm.state.' . $write . '_processor')
            ->args([service('.inner'), service('doctrine')]);
    }

    // Relations written by id as well as by IRI, before the API Platform item denormalizers.
    $services->set('gingerminds_core.api.relation_id_denormalizer', RelationIdDenormalizer::class)
        ->args([
            service('api_platform.resource_class_resolver'),
            service('api_platform.metadata.resource.metadata_collection_factory'),
            service('api_platform.metadata.property.metadata_factory'),
            service('api_platform.iri_converter'),
            service('serializer.name_converter.metadata_aware')->nullOnInvalid(),
        ])
        ->tag('serializer.normalizer', ['priority' => -880]);

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
