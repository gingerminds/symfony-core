<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Bundle;

use Gingerminds\CoreBundle\DependencyInjection\Compiler\OverriddenEntityPass;
use Gingerminds\CoreBundle\DependencyInjection\Compiler\ResourceRegistryPass;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * A bundle shipping overridable entities and admin resources on top of the core.
 *
 * resources() lists them by name: `entity` (the default class) and `interface` (resolved to the
 * configured class by `resolve_target_entities`), then the admin keys (`controller`, `form`,
 * `path`, `permission`, `permissions`); `admin: false` for an entity without admin resource.
 * `entity`, `controller` and `form` are overridable from `<alias>.resources.<name>`.
 *
 * - loadExtension(): the `<alias>.resource.<name>.entity` parameters, the overridden entities
 *   (OverriddenEntityPass), the admin resources (ResourceRegistryPass, below the
 *   `#[AsCrudController]` attributes and the `gingerminds_core.resources` configuration);
 * - prependExtension(): the Doctrine mappings of entityDirectories() and `resolve_target_entities`.
 *
 * A subclass overriding them calls the parent.
 */
abstract class AbstractResourceBundle extends AbstractBundle
{
    private const array OVERRIDABLE_KEYS = ['entity', 'controller', 'form'];

    /**
     * @param array<string, mixed> $config the bundle configuration, resolveConfig() keys only while prepending
     *
     * @return array<string, array<string, mixed>>
     */
    abstract protected function resources(array $config): array;

    /**
     * The directories of `src/Entity` mapped by Doctrine (attributes).
     *
     * @return list<string>
     */
    abstract protected function entityDirectories(): array;

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $parameters = $container->parameters();

        foreach ($this->resources($config) as $name => $resource) {
            $entity = $this->resourceValue($config, $name, 'entity', $resource);
            $parameters->set($this->extensionAlias . '.resource.' . $name . '.entity', $entity);

            if ($entity !== $resource['entity']) {
                OverriddenEntityPass::registerOverriddenEntity($builder, $resource['entity']);
            }

            if (false === ($resource['admin'] ?? true)) {
                continue;
            }

            ResourceRegistryPass::registerBundleResource($builder, $name, array_filter([
                'entity' => $entity,
                'controller' => $this->resourceValue($config, $name, 'controller', $resource),
                'form' => $this->resourceValue($config, $name, 'form', $resource),
                'path' => $resource['path'] ?? null,
                'permission' => $resource['permission'] ?? null,
                'permissions' => $resource['permissions'] ?? null,
                'route_prefix' => $this->extensionAlias . '_' . $name,
                'translation_prefix' => $name,
                'translation_domain' => $this->getTranslationDomain(),
                'template_prefix' => '@' . $this->getTranslationDomain() . '/pages/' . $name,
            ], static fn (mixed $value): bool => null !== $value && [] !== $value));
        }
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $config = $this->resolveConfig($builder);
        $resolveTargetEntities = [];
        $mappings = [];

        foreach ($this->resources($config) as $name => $resource) {
            if (isset($resource['interface'])) {
                $resolveTargetEntities[$resource['interface']] = $this->resourceValue($config, $name, 'entity', $resource);
            }
        }

        foreach ($this->entityDirectories() as $directory) {
            $mappings[$this->getTranslationDomain() . str_replace('/', '', $directory)] = [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => $this->getPath() . '/src/Entity/' . $directory,
                'prefix' => $this->getNamespace() . '\\Entity\\' . str_replace('/', '\\', $directory),
            ];
        }

        $builder->prependExtensionConfig('doctrine', ['orm' => [
            'resolve_target_entities' => $resolveTargetEntities,
            'mappings' => $mappings,
        ]]);
    }

    /**
     * A value of the raw configuration of an extension (another bundle's too) while prepending:
     * the last non-empty one of `$path` (`translation.log_channel`), `$default` when none.
     */
    public static function extensionConfigValue(ContainerBuilder $builder, string $extension, string $path, mixed $default = null): mixed
    {
        $value = $default;

        foreach ($builder->getExtensionConfig($extension) as $config) {
            foreach (explode('.', $path) as $key) {
                if (!\is_array($config) || !\array_key_exists($key, $config)) {
                    continue 2;
                }

                $config = $config[$key];
            }

            if (null !== $config && '' !== $config && [] !== $config) {
                $value = $config;
            }
        }

        return $value;
    }

    /**
     * The translation domain, Twig namespace and Doctrine mapping prefix: the bundle name without
     * `Bundle` (`GingermindsCore`).
     */
    protected function getTranslationDomain(): string
    {
        return (string) preg_replace('/Bundle$/', '', $this->getName());
    }

    /**
     * The configuration keys resolveConfig() processes while prepending; null for all. The other
     * keys may hold env placeholders, not resolvable before the extensions are loaded.
     *
     * @return list<string>|null
     */
    protected function prependConfigKeys(): ?array
    {
        return ['resources'];
    }

    /**
     * The bundle configuration while prepending the other extensions (prependConfigKeys() only).
     *
     * @return array<string, mixed>
     */
    protected function resolveConfig(ContainerBuilder $builder): array
    {
        $extension = $this->getContainerExtension();
        $configuration = $extension instanceof ConfigurationExtensionInterface ? $extension->getConfiguration([], $builder) : null;

        if (!$configuration instanceof ConfigurationInterface) {
            throw new LogicException(\sprintf('The %s configuration cannot be resolved.', $this->getName()));
        }

        $keys = $this->prependConfigKeys();
        $configs = $builder->getExtensionConfig($this->extensionAlias);

        if (null !== $keys) {
            $configs = array_map(static fn (array $config): array => array_intersect_key($config, array_flip($keys)), $configs);
        }

        return new Processor()->processConfiguration($configuration, $builder->getParameterBag()->resolveValue($configs));
    }

    /**
     * The configured `<alias>.resources.<name>.<key>` (`entity`, `controller`, `form`), else the
     * resource's.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $resource
     */
    protected function resourceValue(array $config, string $name, string $key, array $resource): ?string
    {
        $value = \in_array($key, self::OVERRIDABLE_KEYS, true) ? ($config['resources'][$name][$key] ?? null) : null;

        if (\is_string($value) && '' !== $value) {
            return $value;
        }

        return isset($resource[$key]) && \is_string($resource[$key]) ? $resource[$key] : null;
    }
}
