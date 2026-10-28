<?php

declare(strict_types=1);

use Gingerminds\CoreBundle\Resource\RedirectTarget;
use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

/*
 * Admin resources, their permissions, redirects and admin layout includes.
 */
return static function (DefinitionConfigurator $definition): void {
    $definition->rootNode()
        ->children()
            ->arrayNode('permissions')
                ->info('Extra permission names created by gingerminds:permissions:sync.')
                ->scalarPrototype()->end()
                ->defaultValue([])
            ->end()
            ->arrayNode('admin_includes')
                ->info(
                    'Templates included in the admin layout, by slot: "head" (end of <head>, after the admin assets), '
                    . '"sidebar" (under the logo), "sidebar_bottom" (above the user menu).',
                )
                ->useAttributeAsKey('slot')
                ->arrayPrototype()
                    ->scalarPrototype()->end()
                ->end()
                ->defaultValue([])
            ->end()
            ->arrayNode('redirect_after_save')
                ->info('Where the admin goes after creating (new) / updating (edit) a resource: its list (index) or its form (edit). Overridden per resource.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->enumNode('new')->values(array_column(RedirectTarget::cases(), 'value'))->defaultValue(RedirectTarget::Index->value)->end()
                    ->enumNode('edit')->values(array_column(RedirectTarget::cases(), 'value'))->defaultValue(RedirectTarget::Edit->value)->end()
                ->end()
            ->end()
            ->arrayNode('resources')
                ->info('Admin resources. Core ones (user, contributor, role, permission) can be overridden key by key.')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('entity')->defaultNull()->end()
                        ->scalarNode('controller')->defaultNull()->end()
                        ->scalarNode('form')->defaultNull()->end()
                        ->scalarNode('path')->defaultNull()->end()
                        ->scalarNode('permission')->defaultNull()->end()
                        ->scalarNode('route_prefix')->defaultNull()->end()
                        ->scalarNode('translation_prefix')->defaultNull()->end()
                        ->scalarNode('translation_domain')->defaultNull()->end()
                        ->scalarNode('template_prefix')->defaultNull()->end()
                        ->booleanNode('api')
                            ->info('Bundle resources: false stops exposing the entity through the API (the bundle #[ApiResource], inherited by a project entity overriding it).')
                            ->defaultNull()
                        ->end()
                        ->enumNode('redirect_after_new')->values(array_column(RedirectTarget::cases(), 'value'))->defaultNull()->end()
                        ->enumNode('redirect_after_edit')->values(array_column(RedirectTarget::cases(), 'value'))->defaultNull()->end()
                        ->arrayNode('permissions')
                            ->info('Permission name by voter attribute (VIEW, CREATE, EDIT, DELETE), replacing "view|edit|edit|delete <permission>", e.g. {DELETE: "edit files"}.')
                            ->useAttributeAsKey('attribute')
                            ->scalarPrototype()->end()
                            ->validate()
                                ->ifTrue(static fn (array $permissions): bool => [] !== array_diff(array_keys($permissions), ResourceVoter::ATTRIBUTES))
                                ->thenInvalid('The permission keys must be voter attributes: ' . implode(', ', ResourceVoter::ATTRIBUTES) . '; %s given.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->defaultValue([])
            ->end()
        ->end();
};
