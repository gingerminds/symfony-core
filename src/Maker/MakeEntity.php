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
use Symfony\Component\Console\Input\InputOption;

/**
 * make:gm:entity Namespace/Name [--api]: `App\Entity\Namespace\Name`, a
 * resource entity (sortable, searchable, timestampable) with an `id` and a
 * `name`. `--api` adds the #[ApiResource] wired to the generated
 * provider/processor and secured by the resource voter.
 */
final class MakeEntity extends AbstractResourceMaker
{
    public static function getCommandName(): string
    {
        return 'make:gm:entity';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a Gingerminds resource entity (optionally exposed with API Platform)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $this->addNameArgument($command);
        $command->addOption('api', null, InputOption::VALUE_NONE, 'Expose the entity with API Platform (#[ApiResource] + serialization groups)');
    }

    public function configureDependencies(DependencyBuilder $dependencies, ?InputInterface $input = null): void
    {
        parent::configureDependencies($dependencies, $input);

        if (true === $input?->getOption('api')) {
            $dependencies->addClassDependency(ApiResource::class, 'api');
        }
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $resource = $this->getResourceName($input, $generator);
        $api = (bool) $input->getOption('api');
        $this->resourceGenerator->generateEntity($generator, $io, $resource, $api);

        $nextSteps = [
            ...$this->entityNextSteps($resource),
            \sprintf(
                'It needs <comment>%s</comment>: <fg=yellow>bin/console make:gm:repository %s</> (or make:gm:resource for every layer).',
                $resource->repositoryClass(),
                $resource->argument(),
            ),
        ];

        if ($api) {
            $nextSteps[] = \sprintf(
                'The API needs <comment>%s</comment> and <comment>%s</comment>: <fg=yellow>bin/console make:gm:api %s</>.',
                $resource->providerClass(),
                $resource->processorClass(),
                $resource->argument(),
            );
        }

        $this->finish($generator, $io, $nextSteps);
    }
}
