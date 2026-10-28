<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\ApiPlatform\Doctrine\IdentifierOrderExtension;
use Gingerminds\CoreBundle\ApiPlatform\Filter\ComputedFilterStore;
use Gingerminds\CoreBundle\ApiPlatform\Filter\DisplayDateFilter;
use Gingerminds\CoreBundle\ApiPlatform\Filter\EnumFilter;
use Gingerminds\CoreBundle\ApiPlatform\Filter\FilterParameters;
use Gingerminds\CoreBundle\ApiPlatform\Filter\NullableBooleanFilter;
use Gingerminds\CoreBundle\ApiPlatform\Filter\RelationFilter;
use Gingerminds\CoreBundle\ApiPlatform\Filter\SearchableFilter;
use Gingerminds\CoreBundle\ApiPlatform\Metadata\ContextHeaderParametersResourceMetadataCollectionFactory;
use Gingerminds\CoreBundle\ApiPlatform\Metadata\CoreResourceNameCollectionFactory;
use Gingerminds\CoreBundle\ApiPlatform\Metadata\FilterParametersResourceMetadataCollectionFactory;
use Gingerminds\CoreBundle\ApiPlatform\Metadata\HeaderParameterRegistry;
use Gingerminds\CoreBundle\ApiPlatform\Serializer\ComputedFiltersCollectionNormalizer;
use Gingerminds\CoreBundle\ApiPlatform\Serializer\RelationIdDenormalizer;
use Gingerminds\CoreBundle\ApiPlatform\State\RepositoryProcessor;

/*
 * API Platform list filters and parameters, write processors, relation denormalizer, metadata
 * decorators and computed filters. Reads use the native Doctrine providers.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The core filters of the list parameters (FilterParameters), in the API Platform filter locator.
    foreach (
        [
            FilterParameters::BOOLEAN_FILTER => NullableBooleanFilter::class,
            FilterParameters::ENUM_FILTER => EnumFilter::class,
            FilterParameters::RELATION_FILTER => RelationFilter::class,
            FilterParameters::SEARCH_FILTER => SearchableFilter::class,
        ] as $id => $class
    ) {
        $services->set($id, $class)->tag('api_platform.filter');
    }

    $services->set(FilterParameters::DATE_FILTER, DisplayDateFilter::class)
        ->args([service('gingerminds_core.display_timezone')])
        ->tag('api_platform.filter');

    $services->set('gingerminds_core.api.filter_parameters', FilterParameters::class)
        ->args([service('doctrine')]);
    $services->alias(FilterParameters::class, 'gingerminds_core.api.filter_parameters');

    // Before the API Platform parameter factories (998, 1000), which then describe the added
    // parameters (OpenAPI, Hydra, validation): a higher priority decorates closer to the source.
    $services->set('gingerminds_core.api.metadata.filter_parameters', FilterParametersResourceMetadataCollectionFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_collection_factory', null, 1010)
        ->args([
            service('.inner'),
            service('gingerminds_core.api.filter_parameters'),
            service('doctrine'),
        ]);

    $services->set('gingerminds_core.api.doctrine.identifier_order_extension', IdentifierOrderExtension::class)
        ->tag('api_platform.doctrine.orm.query_extension.collection', ['priority' => IdentifierOrderExtension::PRIORITY]);

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

    // Outermost decorator of the Hydra collection normalizer.
    $services->set('gingerminds_core.api.computed_filters_normalizer', ComputedFiltersCollectionNormalizer::class)
        ->decorate('api_platform.hydra.normalizer.collection', null, 10)
        ->args([
            service('.inner'),
            service('gingerminds_core.api.computed_filter_store'),
        ]);
};
