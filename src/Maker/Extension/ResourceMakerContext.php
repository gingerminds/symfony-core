<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Maker\Extension;

use Gingerminds\CoreBundle\Maker\ResourceGenerator;
use Gingerminds\CoreBundle\Maker\ResourceName;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Component\Console\Input\InputInterface;

/**
 * What a maker extension needs to generate its files (ResourceMakerExtensionInterface::generate()).
 */
final readonly class ResourceMakerContext
{
    public function __construct(
        public string $commandName,
        public InputInterface $input,
        public ConsoleStyle $io,
        public Generator $generator,
        public ResourceGenerator $resourceGenerator,
        public ResourceName $resource,
    ) {
    }
}
