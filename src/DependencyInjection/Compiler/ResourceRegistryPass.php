<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\DependencyInjection\Compiler;

use Gingerminds\CoreBundle\GingermindsCoreBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\String\Inflector\EnglishInflector;

use function Symfony\Component\String\u;

final class ResourceRegistryPass implements CompilerPassInterface
{
    public const string CRUD_CONTROLLER_TAG = 'gingerminds_core.crud_controller';
    private const string REGISTRY_ID = 'gingerminds_core.resource_registry';

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

        /** @var array<string, array<string, string|null>> $configured */
        $configured = $container->getParameter('gingerminds_core.resources_config');

        foreach ($configured as $name => $config) {
            $resources[$name] = [...$resources[$name] ?? [], ...array_filter($config, static fn (?string $value): bool => null !== $value)];
        }

        foreach ($resources as $name => $resource) {
            $resources[$name] = $this->withDefaults((string) $name, $resource);
        }

        $container->getDefinition(self::REGISTRY_ID)->setArgument('$resources', $resources);
    }

    /**
     * @param array<string, string|null> $resource
     *
     * @return array<string, string|null>
     */
    private function withDefaults(string $name, array $resource): array
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
        ];
    }
}
