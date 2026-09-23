<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Maker;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping\Entity;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputAwareMakerInterface;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Base of the make:gm:* makers: the `Namespace/Name` argument and the
 * shared ResourceGenerator.
 */
abstract class AbstractResourceMaker extends AbstractMaker implements InputAwareMakerInterface
{
    public function __construct(
        protected readonly ResourceGenerator $resourceGenerator,
    ) {
    }

    public function configureDependencies(DependencyBuilder $dependencies, ?InputInterface $input = null): void
    {
        $dependencies->addClassDependency(Entity::class, 'orm');
    }

    protected function addNameArgument(Command $command): void
    {
        $command->addArgument(
            'name',
            InputArgument::OPTIONAL,
            'Resource as <fg=yellow>Namespace/Name</> (e.g. <fg=yellow>Product/Product</>, <fg=yellow>Media/MediaCategory</>)',
        );
    }

    protected function getResourceName(InputInterface $input, Generator $generator): ResourceName
    {
        return ResourceName::fromInput($input->getArgument('name'), $generator->getRootNamespace());
    }

    /**
     * @param list<string> $nextSteps
     */
    protected function finish(Generator $generator, ConsoleStyle $io, array $nextSteps): void
    {
        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        if ([] !== $nextSteps) {
            $io->text('Next:');
            $io->listing($nextSteps);
        }
    }

    /**
     * @return list<string>
     */
    protected function entityNextSteps(ResourceName $resource): array
    {
        return [
            \sprintf(
                "Add fields with <fg=yellow>bin/console make:entity '%s'</> (a <comment>name</comment> field is generated as a starting point).",
                $resource->relativeClassName(),
            ),
            'Create the table: <fg=yellow>bin/console make:migration</> then <fg=yellow>bin/console doctrine:migrations:migrate</>.',
        ];
    }

    /**
     * @return list<string>
     */
    protected function voterNextSteps(ResourceName $resource): array
    {
        return [
            \sprintf('Create the <comment>view|edit|delete %s</comment> permissions: <fg=yellow>bin/console gingerminds:permissions:sync</>.', $resource->snakePlural),
        ];
    }

    /**
     * `#[ApiResource]` to paste, shown when the entity exists but is not
     * exposed yet (i.e. was not generated with `--api`).
     */
    protected function writeApiResourceSnippet(ConsoleStyle $io, ResourceName $resource): void
    {
        $entity = $resource->entityClass();

        if (class_exists($entity) && [] !== new \ReflectionClass($entity)->getAttributes(ApiResource::class)) {
            return;
        }

        $code = [
            \sprintf('use %s;', $resource->providerClass()),
            \sprintf('use %s;', $resource->processorClass()),
            'use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post};',
            'use Symfony\Component\Serializer\Attribute\Groups;',
            '',
            ...explode("\n", rtrim($this->resourceGenerator->apiResourceSnippet($resource))),
            \sprintf('class %s', $resource->name),
            '{',
            \sprintf("    public const string GROUP_LIST = '%s:list';", $resource->snake),
            \sprintf("    public const string GROUP_READ = '%s:read';", $resource->snake),
            \sprintf("    public const string GROUP_EDIT = '%s:edit';", $resource->snake),
            '',
            '    // + #[Groups([self::GROUP_LIST, self::GROUP_READ, ...])] on each exposed property',
            '}',
        ];

        $io->text(\sprintf('To expose <comment>%s</comment> with API Platform, add:', $entity));
        $io->newLine();

        foreach ($code as $line) {
            $io->writeln(OutputFormatter::escape('    ' . $line));
        }

        $io->newLine();
    }
}
