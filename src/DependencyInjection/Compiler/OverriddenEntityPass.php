<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Bundle entities replaced by a project entity: their file is excluded from the
 * Doctrine attribute mapping and the class from the API Platform resources
 * (`gingerminds_core.overridden_entities`, see CoreResourceNameCollectionFactory).
 *
 * Any bundle registers its own from its extension with registerOverriddenEntity(),
 * whatever the bundle registration order.
 */
final class OverriddenEntityPass implements CompilerPassInterface
{
    public const string TAG = 'gingerminds_core.overridden_entity';
    public const string PARAMETER = 'gingerminds_core.overridden_entities';

    /**
     * @param class-string $defaultEntity the bundle entity replaced by the project one
     */
    public static function registerOverriddenEntity(ContainerBuilder $builder, string $defaultEntity): void
    {
        // Placeholder definition, only read (then removed) by process().
        $builder->register(self::TAG . '.' . hash('xxh128', $defaultEntity), \stdClass::class)
            ->setAbstract(true)
            ->addTag(self::TAG, ['class' => $defaultEntity]);
    }

    public function process(ContainerBuilder $container): void
    {
        $entities = [];

        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            foreach ($tags as $attributes) {
                $class = $attributes['class'] ?? null;

                if (\is_string($class) && class_exists($class)) {
                    $entities[$class] = $class;
                }
            }

            $container->removeDefinition($id);
        }

        $entities = array_values($entities);
        $container->setParameter(self::PARAMETER, $entities);

        if ([] === $entities) {
            return;
        }

        $files = array_map(static fn (string $class): string => (string) new \ReflectionClass($class)->getFileName(), $entities);

        foreach ($container->getDefinitions() as $id => $definition) {
            if (1 === preg_match('/^doctrine\.orm\.[^.]+_attribute_metadata_driver$/', $id)) {
                $definition->addMethodCall('addExcludePaths', [$files]);
            }
        }
    }
}
