<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Maker;

use ApiPlatform\Metadata\ApiResource;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

/**
 * make:gm:resource Namespace/Name [--api] [--no-controller] (Laravel
 * make:resource): entity, repository, form type, voter, admin CRUD
 * controller + templates + translations and, with `--api`, the API
 * provider/processor and #[ApiResource]. Existing files are skipped.
 */
final class MakeResource extends AbstractResourceMaker
{
    public static function getCommandName(): string
    {
        return 'make:gm:resource';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a full Gingerminds resource: entity, repository, form, voter, admin CRUD and optionally API';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $this->addNameArgument($command);
        $command
            ->addOption('api', null, InputOption::VALUE_NONE, 'Also expose the resource with API Platform (provider, processor, #[ApiResource])')
            ->addOption('no-controller', null, InputOption::VALUE_NONE, 'Do not generate the admin CRUD controller, templates and translations');
    }

    public function configureDependencies(DependencyBuilder $dependencies, ?InputInterface $input = null): void
    {
        parent::configureDependencies($dependencies, $input);

        if (true !== $input?->getOption('no-controller')) {
            $dependencies->addClassDependency(TwigBundle::class, 'twig-bundle');
            $dependencies->addClassDependency(Yaml::class, 'yaml');
        }

        if (true === $input?->getOption('api')) {
            $dependencies->addClassDependency(ApiResource::class, 'api');
        }
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $resource = $this->getResourceName($input, $generator);
        $api = (bool) $input->getOption('api');
        $controller = !$input->getOption('no-controller');

        $entityGenerated = $this->resourceGenerator->generateEntity($generator, $io, $resource, $api);
        $this->resourceGenerator->generateRepository($generator, $io, $resource);
        $this->resourceGenerator->generateForm($generator, $io, $resource);
        $this->resourceGenerator->generateVoter($generator, $io, $resource);

        if ($controller) {
            $this->resourceGenerator->generateCrudController($generator, $io, $resource);
        }

        if ($api) {
            $this->resourceGenerator->generateApi($generator, $io, $resource);
        }

        $nextSteps = [...$this->entityNextSteps($resource), ...$this->voterNextSteps($resource)];

        if ($controller) {
            $nextSteps[] = MakeCrudController::nextSteps($resource)[0];
        }

        if ($api) {
            $nextSteps[] = \sprintf('The API is served under <comment>/{api_prefix}/%s</comment>.', $resource->snakePlural);
        }

        $this->finish($generator, $io, $nextSteps);

        if ($api && !$entityGenerated) {
            $this->writeApiResourceSnippet($io, $resource);
        }
    }
}
