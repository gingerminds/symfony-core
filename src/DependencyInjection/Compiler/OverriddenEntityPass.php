<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Bundle entities replaced by a project entity: their file is excluded from the Doctrine
 * attribute mapping, the project entity takes their place in the API Platform resources, with
 * their `#[ApiResource]` unless it declares its own (`gingerminds_core.overridden_entities`, see
 * CoreResourceNameCollectionFactory and OverriddenEntityResourceMetadataCollectionFactory).
 * Bundle entities whose resource is configured with `api: false` are not exposed, nor their
 * project entity (`gingerminds_core.api.disabled_resources`).
 *
 * Any bundle registers its own from its extension with registerOverriddenEntity() and
 * disableApiResource(), whatever the bundle registration order.
 */
final class OverriddenEntityPass implements CompilerPassInterface
{
    public const string TAG = 'gingerminds_core.overridden_entity';
    public const string API_DISABLED_TAG = 'gingerminds_core.api_disabled_entity';
    public const string PARAMETER = 'gingerminds_core.overridden_entities';
    public const string API_DISABLED_PARAMETER = 'gingerminds_core.api.disabled_resources';

    /**
     * @param class-string $defaultEntity the bundle entity replaced by the project one
     * @param string       $entity        the project entity
     */
    public static function registerOverriddenEntity(ContainerBuilder $builder, string $defaultEntity, string $entity): void
    {
        self::register($builder, self::TAG, ['class' => $defaultEntity, 'entity' => $entity]);
    }

    /**
     * @param class-string $defaultEntity the bundle entity no longer exposed by the API, nor its project entity
     */
    public static function disableApiResource(ContainerBuilder $builder, string $defaultEntity): void
    {
        self::register($builder, self::API_DISABLED_TAG, ['class' => $defaultEntity]);
    }

    public function process(ContainerBuilder $container): void
    {
        $entities = [];
        $disabled = [];

        foreach ($this->collect($container, self::TAG) as $attributes) {
            $class = $attributes['class'] ?? null;
            $entity = $attributes['entity'] ?? null;

            if (\is_string($class) && \is_string($entity) && class_exists($class)) {
                $entities[$class] = $entity;
            }
        }

        foreach ($this->collect($container, self::API_DISABLED_TAG) as $attributes) {
            $class = $attributes['class'] ?? null;

            if (\is_string($class)) {
                $disabled[$class] = $class;
                $disabled[$entities[$class] ?? $class] = $entities[$class] ?? $class;
            }
        }

        $container->setParameter(self::PARAMETER, $entities);
        $container->setParameter(self::API_DISABLED_PARAMETER, array_values($disabled));

        if ([] === $entities) {
            return;
        }

        $files = array_map(static fn (string $class): string => (string) new \ReflectionClass($class)->getFileName(), array_keys($entities));

        foreach ($container->getDefinitions() as $id => $definition) {
            if (1 === preg_match('/^doctrine\.orm\.[^.]+_attribute_metadata_driver$/', $id)) {
                $definition->addMethodCall('addExcludePaths', [$files]);
            }
        }
    }

    /**
     * @param array<string, string> $attributes
     */
    private static function register(ContainerBuilder $builder, string $tag, array $attributes): void
    {
        // Placeholder definition, only read (then removed) by process().
        $builder->register($tag . '.' . hash('xxh128', $attributes['class']), \stdClass::class)
            ->setAbstract(true)
            ->addTag($tag, $attributes);
    }

    /**
     * The tag attributes of the placeholder definitions, removed.
     *
     * @return list<array<string, mixed>>
     */
    private function collect(ContainerBuilder $container, string $tag): array
    {
        $collected = [];

        foreach ($container->findTaggedServiceIds($tag) as $id => $tags) {
            array_push($collected, ...$tags);
            $container->removeDefinition($id);
        }

        return $collected;
    }
}
