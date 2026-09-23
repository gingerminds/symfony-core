<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\Cache\ApiResponseCacheListener;
use Gingerminds\CoreBundle\Cache\CacheContextResolverInterface;
use Gingerminds\CoreBundle\Cache\CacheInvalidationListener;
use Gingerminds\CoreBundle\Cache\CacheKeyBuilder;
use Gingerminds\CoreBundle\Cache\NullCacheContextResolver;
use Symfony\Component\HttpKernel\KernelEvents;

/*
 * API response cache and its Doctrine invalidation.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_core.cache.null_context_resolver', NullCacheContextResolver::class);
    $services->alias('gingerminds_core.cache.context_resolver', 'gingerminds_core.cache.null_context_resolver');
    $services->alias(CacheContextResolverInterface::class, 'gingerminds_core.cache.context_resolver');

    $services->set('gingerminds_core.cache.key_builder', CacheKeyBuilder::class)
        ->args([service('gingerminds_core.cache.context_resolver')]);
    $services->alias(CacheKeyBuilder::class, 'gingerminds_core.cache.key_builder');

    $services->set('gingerminds_core.cache.api_response_listener', ApiResponseCacheListener::class)
        ->args([
            service('gingerminds_core.cache.pool'),
            service('gingerminds_core.cache.key_builder'),
            service('api_platform.metadata.resource.metadata_collection_factory'),
            service('api_platform.security.resource_access_checker')->nullOnInvalid(),
            param('gingerminds_core.cache.enabled'),
            param('gingerminds_core.cache.default_ttl'),
        ])
        // After the firewall (8), before API Platform's read listener (4).
        ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onKernelRequest', 'priority' => 5])
        ->tag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'method' => 'onKernelResponse', 'priority' => -10]);

    $services->set('gingerminds_core.cache.invalidation_listener', CacheInvalidationListener::class)
        ->args([
            service('gingerminds_core.cache.pool'),
            service('gingerminds_core.cache.key_builder'),
            param('gingerminds_core.cache.enabled'),
        ])
        ->tag('doctrine.event_listener', ['event' => 'onFlush'])
        ->tag('doctrine.event_listener', ['event' => 'postFlush'])
        ->tag('kernel.reset', ['method' => 'reset']);
};
