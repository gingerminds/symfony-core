<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * make:gm:voter Namespace/Name (Laravel make:policy):
 * `App\Security\Voter\Namespace\NameVoter` extending AbstractResourceVoter
 * (`view|edit|delete <snake plural>` permissions). Autoconfigured by Symfony.
 */
final class MakeVoter extends AbstractResourceMaker
{
    public static function getCommandName(): string
    {
        return 'make:gm:voter';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a Gingerminds resource voter (extends AbstractResourceVoter)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $this->addNameArgument($command);
    }

    public function configureDependencies(DependencyBuilder $dependencies, ?InputInterface $input = null): void
    {
        parent::configureDependencies($dependencies, $input);
        $dependencies->addClassDependency(Voter::class, 'security');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $resource = $this->getResourceName($input, $generator);
        $this->resourceGenerator->generateVoter($generator, $io, $resource);

        $this->finish($generator, $io, $this->voterNextSteps($resource));
    }
}
