<?php

declare(strict_types=1);

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

/*
 * API (authentication routes, tokens, throttling, rate limit) and API response cache.
 */
return static function (DefinitionConfigurator $definition): void {
    $definition->rootNode()
        ->children()
            ->arrayNode('api')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('prefix')
                        ->info('Prefix of the API authentication routes (login/logout).')
                        ->defaultValue('api')
                    ->end()
                    ->integerNode('token_ttl')
                        ->info('API token lifetime in seconds, null for no expiration.')
                        ->defaultNull()
                        ->min(60)
                    ->end()
                    ->arrayNode('login_throttling')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->integerNode('max_attempts')->defaultValue(5)->min(1)->end()
                            ->scalarNode('interval')->defaultValue('5 minutes')->end()
                        ->end()
                    ->end()
                    ->arrayNode('rate_limit')
                        ->info('Rate limit of every request under the API prefix, per user (or IP when anonymous). An operation / route overrides it with its own limiter.')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->booleanNode('enabled')->defaultTrue()->end()
                            ->integerNode('limit')->defaultValue(60)->min(1)->end()
                            ->scalarNode('interval')->defaultValue('1 minute')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('cache')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('enabled')
                        ->info('Global switch of the API response cache of the #[Cacheable] resources.')
                        ->defaultTrue()
                    ->end()
                    ->integerNode('default_ttl')->defaultValue(3600)->min(0)->end()
                    ->scalarNode('pool')
                        ->info('Tag-aware cache pool service id.')
                        ->defaultValue('gingerminds_core.resource_cache')
                    ->end()
                ->end()
            ->end()
        ->end();
};
