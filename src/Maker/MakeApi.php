<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Maker;

use ApiPlatform\Metadata\ApiResource;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * make:gm:api Namespace/Name (Laravel make:api-provider + make:state-processor):
 * `App\State\Namespace\NameProvider` (ResourceProvider). No processor: writes are
 * deserialized and validated by API Platform, persisted by its Doctrine processor through
 * the repository save hooks.
 */
final class MakeApi extends AbstractResourceMaker
{
    public static function getCommandName(): string
    {
        return 'make:gm:api';
    }

    public static function getCommandDescription(): string
    {
        return 'Create the API Platform state provider of a Gingerminds resource';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $this->addNameArgument($command);
        $this->configureExtensions($command);
    }

    public function configureDependencies(DependencyBuilder $dependencies, ?InputInterface $input = null): void
    {
        parent::configureDependencies($dependencies, $input);
        $dependencies->addClassDependency(ApiResource::class, 'api');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $resource = $this->getResourceName($input, $generator);
        $this->generatorFor($input)->generateApi($generator, $io, $resource);

        $this->finish($generator, $io, [
            \sprintf('It requires <comment>%s</comment>.', $resource->repositoryClass()),
        ], $input, $resource);

        $this->writeApiResourceSnippet($io, $resource);
    }
}
