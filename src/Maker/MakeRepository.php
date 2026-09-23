<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * make:gm:repository Namespace/Name: `App\Repository\Namespace\NameRepository`
 * extending AbstractRepository (pagination, search, filters, sort, save hooks).
 */
final class MakeRepository extends AbstractResourceMaker
{
    public static function getCommandName(): string
    {
        return 'make:gm:repository';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a Gingerminds resource repository (extends AbstractRepository)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $this->addNameArgument($command);
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $resource = $this->getResourceName($input, $generator);
        $this->resourceGenerator->generateRepository($generator, $io, $resource);

        $this->finish($generator, $io, [
            \sprintf('Point the entity to it: <comment>#[ORM\Entity(repositoryClass: %s::class)]</comment>.', ResourceGenerator::shortName($resource->repositoryClass())),
        ]);
    }
}
