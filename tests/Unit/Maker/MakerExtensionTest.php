<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\Maker;

use Composer\Autoload\ClassLoader;
use Gingerminds\CoreBundle\Maker\Extension\ResourceMakerContext;
use Gingerminds\CoreBundle\Maker\Extension\ResourceMakerExtensionInterface;
use Gingerminds\CoreBundle\Maker\Extension\SkeletonTemplate;
use Gingerminds\CoreBundle\Maker\MakeResource;
use Gingerminds\CoreBundle\Maker\ResourceGenerator;
use Gingerminds\CoreBundle\Maker\ResourceName;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Util\AutoloaderUtil;
use Symfony\Bundle\MakerBundle\Util\ComposerAutoloaderFinder;
use Symfony\Bundle\MakerBundle\Util\MakerFileLinkFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

final class MakerExtensionTest extends TestCase
{
    private string $directory;

    private string $namespace;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/gingerminds-maker-' . bin2hex(random_bytes(4));
        new Filesystem()->mkdir($this->directory . '/src');

        // The MakerBundle resolves the path of the generated classes from the Composer autoloader.
        $namespace = 'MakerTest' . bin2hex(random_bytes(4));
        $this->loader()->addPsr4($namespace . '\\', $this->directory . '/src/');
        $this->namespace = $namespace;
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testExtensionAddsAnOptionChangesSkeletonsAndGeneratesFiles(): void
    {
        $extension = new TestMakerExtension();
        $maker = new MakeResource(new ResourceGenerator(), [$extension]);

        $command = new Command(MakeResource::getCommandName());
        $maker->configureCommand($command, new InputConfiguration());
        self::assertTrue($command->getDefinition()->hasOption('flagged'));

        $input = new ArrayInput(['name' => 'Catalog/Product', '--flagged' => true], $command->getDefinition());
        $output = new BufferedOutput();
        $maker->generate($input, new ConsoleStyle($input, $output), $this->generator());

        $entity = (string) file_get_contents($this->directory . '/src/Entity/Catalog/Product.php');
        self::assertStringContainsString('use Countable;', $entity);
        self::assertStringContainsString('implements ResourceInterface, SearchableInterface, SortableInterface, TimestampableInterface, Countable, EagerLoadableInterface, \Stringable' . "\n{", $entity);
        self::assertStringContainsString('use Gingerminds\CoreBundle\Model\EagerLoadableInterface;', $entity);
        self::assertStringContainsString("return ['category'];", $entity);

        $form = (string) file_get_contents($this->directory . '/src/Form/Catalog/ProductType.php');
        self::assertStringContainsString("\$builder->add('flagged');", $form);
        self::assertStringNotContainsString('TODO', $form);

        self::assertFileExists($this->directory . '/src/Entity/Catalog/ProductExtra.php');
        self::assertStringContainsString('Run the extra step.', $output->fetch());
        self::assertSame(['make:gm:resource'], $extension->generatedFor);
    }

    public function testExtensionIsIgnoredWithoutItsOption(): void
    {
        $extension = new TestMakerExtension();
        $maker = new MakeResource(new ResourceGenerator(), [$extension]);

        $command = new Command(MakeResource::getCommandName());
        $maker->configureCommand($command, new InputConfiguration());

        $input = new ArrayInput(['name' => 'Catalog/Product'], $command->getDefinition());
        $maker->generate($input, new ConsoleStyle($input, new BufferedOutput()), $this->generator());

        $entity = (string) file_get_contents($this->directory . '/src/Entity/Catalog/Product.php');
        self::assertStringNotContainsString('Countable', $entity);
        self::assertStringNotContainsString('public static function getEagerLoads', $entity);
        self::assertFileDoesNotExist($this->directory . '/src/Entity/Catalog/ProductExtra.php');
        self::assertSame([], $extension->generatedFor);
    }

    private function generator(): Generator
    {
        $fileManager = new FileManager(
            new Filesystem(),
            new AutoloaderUtil(new ComposerAutoloaderFinder($this->namespace)),
            new MakerFileLinkFormatter(),
            $this->directory,
            $this->directory . '/templates',
        );

        return new Generator($fileManager, $this->namespace);
    }

    private function loader(): ClassLoader
    {
        foreach (spl_autoload_functions() as $function) {
            if (\is_array($function) && $function[0] instanceof ClassLoader) {
                return $function[0];
            }
        }

        self::fail('No Composer class loader registered.');
    }
}

final class TestMakerExtension implements ResourceMakerExtensionInterface
{
    /** @var list<string> */
    public array $generatedFor = [];

    public function configureCommand(string $commandName, Command $command): void
    {
        $command->addOption('flagged', null, InputOption::VALUE_NONE, 'Test option');
    }

    public function isEnabled(InputInterface $input): bool
    {
        return true === $input->getOption('flagged');
    }

    public function configureTemplate(SkeletonTemplate $template, ResourceName $resource): void
    {
        match ($template->name) {
            'Entity.tpl.php' => $this->configureEntity($template),
            'FormType.tpl.php' => $template->append('build_form', "        \$builder->add('flagged');"),
            default => null,
        };
    }

    public function generate(ResourceMakerContext $context): array
    {
        $this->generatedFor[] = $context->commandName;

        $skeleton = new SkeletonTemplate('extra', $context->resourceGenerator->getSkeletonDirectory() . '/Repository.tpl.php', [
            'entity_class' => 'Product',
        ]);
        $context->resourceGenerator->generateClassFromSkeleton($context->generator, $context->io, $context->resource->entityClass() . 'Extra', $skeleton);

        return ['Run the extra step.'];
    }

    private function configureEntity(SkeletonTemplate $template): void
    {
        $template->addUse('Countable');
        $template->append('interfaces', 'Countable');
        $template->append('eager_loads', "'category'");
    }
}
