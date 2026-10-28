<?php

declare(strict_types=1);

use Gingerminds\CoreBundle\Form\User\ProfileType;
use Gingerminds\CoreBundle\Service\DisplayTimezone;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Form\FormTypeInterface;

/*
 * Admin area, health check, display timezone, profile page and login form.
 */
return static function (DefinitionConfigurator $definition): void {
    $definition->rootNode()
        ->children()
            ->scalarNode('admin_prefix')
                ->info('URL prefix of the whole admin area (routes, firewall access_control).')
                ->defaultValue('admin')
                ->cannotBeEmpty()
            ->end()
            ->scalarNode('admin_title')
                ->info('Translation key (or plain text) of the title displayed in the admin layout.')
                ->defaultValue('admin.title')
            ->end()
            ->scalarNode('admin_title_translation_domain')
                ->info('Translation domain of admin_title.')
                ->defaultValue('GingermindsCore')
            ->end()
            ->scalarNode('health_check_path')
                ->info('Public path always answering {"status": "ok"} (CI/monitoring).')
                ->defaultValue('health')
                ->cannotBeEmpty()
            ->end()
            ->scalarNode('display_timezone')
                ->info('Timezone of the dates typed and displayed (forms, Twig "date" filter, date filters and facets). Dates are always stored in UTC.')
                ->defaultValue(DisplayTimezone::DEFAULT)
                ->cannotBeEmpty()
            ->end()
            ->arrayNode('profile')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('form')
                        ->info('Form type of the current user\'s profile page. The bundle template renders the ProfileType fields (email, password, contributor*).')
                        ->defaultValue(ProfileType::class)
                        ->cannotBeEmpty()
                        ->validate()
                            ->ifTrue(static fn (string $class): bool => !is_a($class, FormTypeInterface::class, true))
                            ->thenInvalid('The profile form must be a class implementing ' . FormTypeInterface::class . ', %s given.')
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('security')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('authorized_domains')
                        ->info('Hosts allowed as "Origin" of the admin login form. Empty list disables the check.')
                        ->scalarPrototype()->end()
                        ->defaultValue([])
                    ->end()
                ->end()
            ->end()
        ->end();
};
