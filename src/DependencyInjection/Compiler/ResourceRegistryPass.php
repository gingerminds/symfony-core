<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\DependencyInjection\Compiler;

use Gingerminds\CoreBundle\GingermindsCoreBundle;
use Gingerminds\CoreBundle\Resource\RedirectTarget;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\String\Inflector\EnglishInflector;

use function Symfony\Component\String\u;

final class ResourceRegistryPass implements CompilerPassInterface
{
    public const string CRUD_CONTROLLER_TAG = 'gingerminds_core.crud_controller';
    private const string REGISTRY_ID = 'gingerminds_core.resource_registry';
    private const string ROUTING_CONTROLLER_TAG = 'routing.controller';
    private const string AUTOCOMPLETER_ID = 'gingerminds_core.form.autocompleter';
    private const string UX_AUTOCOMPLETER_TAG = 'ux.entity_autocompleter';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::REGISTRY_ID)) {
            return;
        }

        $resources = [];

        foreach (GingermindsCoreBundle::CORE_RESOURCES as $name => $core) {
            $resources[$name] = [
                'entity' => $core['entity'],
                'controller' => $core['controller'],
                'form' => $core['form'],
                'path' => $core['path'],
                'permission' => $core['permission'],
                'route_prefix' => 'gingerminds_core_' . $name,
                'translation_prefix' => $name,
                'translation_domain' => 'GingermindsCore',
                'template_prefix' => '@GingermindsCore/pages/' . $name,
                'permissions' => $core['permissions'] ?? [],
            ];
        }

        foreach ($container->findTaggedServiceIds(self::CRUD_CONTROLLER_TAG) as $id => $tags) {
            foreach ($tags as $attributes) {
                $name = $attributes['resource'] ?? throw new InvalidArgumentException(\sprintf(
                    'The "%s" tag of service "%s" requires a "resource" attribute.',
                    self::CRUD_CONTROLLER_TAG,
                    $id,
                ));
                unset($attributes['resource']);

                $resources[$name] = [...$resources[$name] ?? [], ...$attributes, 'controller' => $id];
            }
        }

        /** @var array<string, array<string, array<string, string>|string|null>> $configured */
        $configured = $container->getParameter('gingerminds_core.resources_config');

        foreach ($configured as $name => $config) {
            $resources[$name] = [...$resources[$name] ?? [], ...array_filter($config, static fn (array|string|null $value): bool => null !== $value && [] !== $value)];
        }

        /** @var array{new: string, edit: string} $redirectAfterSave */
        $redirectAfterSave = $container->hasParameter('gingerminds_core.redirect_after_save')
            ? $container->getParameter('gingerminds_core.redirect_after_save')
            : ['new' => RedirectTarget::Index->value, 'edit' => RedirectTarget::Edit->value];

        foreach ($resources as $name => $resource) {
            $resources[$name] = $this->withDefaults((string) $name, $resource, $redirectAfterSave);
        }

        $container->getDefinition(self::REGISTRY_ID)->setArgument('$resources', $resources);
        $this->excludeFromRoutingControllers($container, $resources);
        $this->registerAutocompleters($container, array_keys($resources));
    }

    /**
     * A UX Autocomplete source per resource, aliased by its name. This pass runs before
     * AutocompleteFormTypePass, which collects the tag.
     *
     * @param list<int|string> $names
     */
    private function registerAutocompleters(ContainerBuilder $container, array $names): void
    {
        if (!$container->hasDefinition(self::AUTOCOMPLETER_ID)) {
            return;
        }

        foreach ($names as $name) {
            $container->setDefinition(self::AUTOCOMPLETER_ID . '.' . $name, new ChildDefinition(self::AUTOCOMPLETER_ID))
                ->replaceArgument(0, (string) $name)
                ->addTag(self::UX_AUTOCOMPLETER_TAG, ['alias' => (string) $name]);
        }
    }

    /**
     * The CRUD controllers are routed by CrudRouteLoader, under their resource path: the
     * `routing.controllers` import must not load their #[Route] (inherited from
     * AbstractCrudController) a second time, without prefix. This pass runs before
     * RoutingControllerPass.
     *
     * @param array<string, array<string, mixed>> $resources
     */
    private function excludeFromRoutingControllers(ContainerBuilder $container, array $resources): void
    {
        $routed = [];

        foreach ($resources as $resource) {
            if (\is_string($resource['controller'] ?? null)) {
                $routed[$resource['controller']] = true;
            }
        }

        foreach (array_keys($container->findTaggedServiceIds(self::ROUTING_CONTROLLER_TAG)) as $id) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass());

            if (isset($routed[$id]) || (\is_string($class) && isset($routed[$class]))) {
                $container->getDefinition($id)->clearTag(self::ROUTING_CONTROLLER_TAG);
            }
        }
    }

    /**
     * @param array<string, mixed>             $resource
     * @param array{new: string, edit: string} $redirectAfterSave global defaults
     *
     * @return array<string, mixed>
     */
    private function withDefaults(string $name, array $resource, array $redirectAfterSave): array
    {
        if (!isset($resource['entity'])) {
            throw new InvalidArgumentException(\sprintf('The resource "%s" has no "entity".', $name));
        }

        $plural = new EnglishInflector()->pluralize(u($name)->snake()->toString())[0];

        return [
            'entity' => $resource['entity'],
            'controller' => $resource['controller'] ?? null,
            'form' => $resource['form'] ?? null,
            'path' => $resource['path'] ?? u($plural)->replace('_', '-')->toString(),
            'permission' => $resource['permission'] ?? $plural,
            'route_prefix' => $resource['route_prefix'] ?? 'admin_' . u($name)->snake()->toString(),
            'translation_prefix' => $resource['translation_prefix'] ?? u($name)->snake()->toString(),
            'translation_domain' => $resource['translation_domain'] ?? 'messages',
            'template_prefix' => $resource['template_prefix'] ?? 'admin/' . u($name)->snake()->toString(),
            'redirect_after_new' => $resource['redirect_after_new'] ?? $redirectAfterSave['new'],
            'redirect_after_edit' => $resource['redirect_after_edit'] ?? $redirectAfterSave['edit'],
            'permissions' => $resource['permissions'] ?? [],
        ];
    }
}
