<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\DependencyInjection\Compiler;

use Gingerminds\CoreBundle\Repository\AbstractRepository;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Gives every AbstractRepository service the API Platform ParameterExtension applying the list
 * parameters: the bundle repositories are declared without autowiring (#[Required]).
 */
final class RepositoryParameterExtensionPass implements CompilerPassInterface
{
    private const string PARAMETER_EXTENSION = 'api_platform.doctrine.orm.extension.parameter_extension';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(self::PARAMETER_EXTENSION)) {
            return;
        }

        foreach ($container->getDefinitions() as $definition) {
            $class = $container->getParameterBag()->resolveValue($definition->getClass());

            if ($definition->isAbstract() || !\is_string($class) || $definition->hasMethodCall('setParameterExtension')) {
                continue;
            }

            // A class that cannot be loaded (optional dependency) is not a repository.
            $reflection = $container->getReflectionClass($class, false);

            if (!$reflection instanceof \ReflectionClass || !$reflection->isSubclassOf(AbstractRepository::class)) {
                continue;
            }

            $definition->addMethodCall('setParameterExtension', [new Reference(self::PARAMETER_EXTENSION)]);
        }
    }
}
