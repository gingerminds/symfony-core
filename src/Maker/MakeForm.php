<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Form\AbstractType;

/**
 * make:gm:form Namespace/Name: `App\Form\Namespace\NameType`, the form shared
 * by the admin CRUD and the API processor (translation domain `admin`).
 */
final class MakeForm extends AbstractResourceMaker
{
    public static function getCommandName(): string
    {
        return 'make:gm:form';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a Gingerminds resource form type (admin + API)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $this->addNameArgument($command);
    }

    public function configureDependencies(DependencyBuilder $dependencies, ?InputInterface $input = null): void
    {
        parent::configureDependencies($dependencies, $input);
        $dependencies->addClassDependency(AbstractType::class, 'form');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $resource = $this->getResourceName($input, $generator);
        $this->resourceGenerator->generateForm($generator, $io, $resource);

        $this->finish($generator, $io, [
            \sprintf('Add a field per entity property; labels are <comment>%s.field.*</comment> in <comment>translations/admin.*.yaml</comment>.', $resource->snake),
        ]);
    }
}
