<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CoreEntityPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('gingerminds_core.excluded_entity_files')) {
            return;
        }

        /** @var list<string> $files */
        $files = $container->getParameter('gingerminds_core.excluded_entity_files');

        if ([] === $files) {
            return;
        }

        foreach ($container->getDefinitions() as $id => $definition) {
            if (1 === preg_match('/^doctrine\.orm\.[^.]+_attribute_metadata_driver$/', $id)) {
                $definition->addMethodCall('addExcludePaths', [$files]);
            }
        }
    }
}
