<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Maker;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\ApiPlatform\State\ResourceProcessor;
use Gingerminds\CoreBundle\ApiPlatform\State\ResourceProvider;
use Gingerminds\CoreBundle\Controller\AbstractCrudController;
use Gingerminds\CoreBundle\Maker\Extension\ResourceMakerExtensionInterface;
use Gingerminds\CoreBundle\Maker\Extension\SkeletonTemplate;
use Gingerminds\CoreBundle\Model\EagerLoadableInterface;
use Gingerminds\CoreBundle\Model\ResourceInterface;
use Gingerminds\CoreBundle\Model\SearchableInterface;
use Gingerminds\CoreBundle\Model\SortableInterface;
use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\CoreBundle\Model\Trait\TimestampableTrait;
use Gingerminds\CoreBundle\Repository\AbstractRepository;
use Gingerminds\CoreBundle\Resource\AsCrudController;
use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class ResourceGenerator
{
    private const array LOCALES = ['fr', 'en'];

    /**
     * Skip reason of a file that is already there, also the wording of the MakerBundle "already exists" errors.
     */
    private const string ALREADY_EXISTS = 'already exists';

    private string $skeletonDirectory;

    /**
     * @param list<ResourceMakerExtensionInterface> $extensions extensions enabled for the current run (see withExtensions())
     */
    public function __construct(
        ?string $skeletonDirectory = null,
        private array $extensions = [],
    ) {
        $this->skeletonDirectory = $skeletonDirectory ?? __DIR__ . '/skeleton';
    }

    /**
     * Same generator, the skeletons changed by the given (enabled) extensions.
     *
     * @param list<ResourceMakerExtensionInterface> $extensions
     */
    public function withExtensions(array $extensions): self
    {
        return new self($this->skeletonDirectory, $extensions);
    }

    public function getSkeletonDirectory(): string
    {
        return $this->skeletonDirectory;
    }

    public function generateEntity(Generator $generator, ConsoleStyle $io, ResourceName $resource, bool $api): bool
    {
        $uses = [
            'Doctrine\\ORM\\Mapping as ORM',
            $resource->repositoryClass(),
            ResourceInterface::class,
            SearchableInterface::class,
            SortableInterface::class,
            TimestampableInterface::class,
            TimestampableTrait::class,
        ];

        if ($api) {
            $uses[] = ApiResource::class;
            $uses[] = Delete::class;
            $uses[] = Get::class;
            $uses[] = GetCollection::class;
            $uses[] = Patch::class;
            $uses[] = Post::class;
            $uses[] = Groups::class;
            $uses[] = $resource->providerClass();
            $uses[] = $resource->processorClass();
        }

        $skeleton = $this->skeleton('Entity.tpl.php', $resource, [
            'resource' => $resource,
            'api' => $api,
            'repository_class' => self::shortName($resource->repositoryClass()),
            'provider_class' => self::shortName($resource->providerClass()),
            'processor_class' => self::shortName($resource->processorClass()),
            // Extra implemented interfaces / used traits (short names, imported with `uses`).
            'interfaces' => [],
            'traits' => [],
            // Non-empty: the entity implements EagerLoadableInterface and returns these
            // paths (PHP expressions, e.g. `'category'` or `...self::getTranslationEagerLoads()`).
            'eager_loads' => [],
        ], $uses);

        if ([] !== $skeleton->variables['eager_loads']) {
            $skeleton->addUse(EagerLoadableInterface::class);
        }

        return $this->generateClassFromSkeleton($generator, $io, $resource->entityClass(), $skeleton);
    }

    public function generateRepository(Generator $generator, ConsoleStyle $io, ResourceName $resource): bool
    {
        return $this->generateClassFromSkeleton($generator, $io, $resource->repositoryClass(), $this->skeleton('Repository.tpl.php', $resource, [
            'entity_class' => self::shortName($resource->entityClass()),
        ], [
            ManagerRegistry::class,
            AbstractRepository::class,
            $resource->entityClass(),
        ]));
    }

    public function generateForm(Generator $generator, ConsoleStyle $io, ResourceName $resource): bool
    {
        return $this->generateClassFromSkeleton($generator, $io, $resource->formClass(), $this->skeleton('FormType.tpl.php', $resource, [
            'resource' => $resource,
            'entity_class' => self::shortName($resource->entityClass()),
            'build_form' => [],
        ], [
            AbstractType::class,
            FormBuilderInterface::class,
            OptionsResolver::class,
            $resource->entityClass(),
        ]));
    }

    /**
     * Controller, admin templates and admin translations.
     */
    public function generateCrudController(Generator $generator, ConsoleStyle $io, ResourceName $resource): void
    {
        $this->generateClassFromSkeleton($generator, $io, $resource->controllerClass(), $this->skeleton('CrudController.tpl.php', $resource, [
            'resource' => $resource,
            'entity_class' => self::shortName($resource->entityClass()),
            'form_class' => self::shortName($resource->formClass()),
        ], [
            AbstractCrudController::class,
            AsCrudController::class,
            $resource->entityClass(),
            $resource->formClass(),
        ]));

        foreach (['index', 'new', 'edit', '_form'] as $template) {
            $this->generateTemplate($generator, $io, $resource, $template);
        }

        $this->addTranslations($generator, $io, 'admin', [
            $resource->snake => [
                'name_s' => $resource->label(),
                'name_p' => $resource->label(true),
                'field' => ['id' => 'ID'],
            ],
        ]);
    }

    public function generateVoter(Generator $generator, ConsoleStyle $io, ResourceName $resource): bool
    {
        return $this->generateClassFromSkeleton($generator, $io, $resource->voterClass(), $this->skeleton('Voter.tpl.php', $resource, [
            'resource' => $resource,
            'entity_class' => self::shortName($resource->entityClass()),
        ], [
            AbstractResourceVoter::class,
            $resource->entityClass(),
        ]));
    }

    /**
     * API Platform state provider and processor.
     */
    public function generateApi(Generator $generator, ConsoleStyle $io, ResourceName $resource): void
    {
        $this->generateClassFromSkeleton($generator, $io, $resource->providerClass(), $this->skeleton('Provider.tpl.php', $resource, [
            'entity_class' => self::shortName($resource->entityClass()),
            'repository_class' => self::shortName($resource->repositoryClass()),
        ], [
            ResourceProvider::class,
            $resource->entityClass(),
            $resource->repositoryClass(),
            RequestStack::class,
        ]));

        $this->generateClassFromSkeleton($generator, $io, $resource->processorClass(), $this->skeleton('Processor.tpl.php', $resource, [
            'entity_class' => self::shortName($resource->entityClass()),
            'repository_class' => self::shortName($resource->repositoryClass()),
            'form_class' => self::shortName($resource->formClass()),
        ], [
            ResourceProcessor::class,
            $resource->entityClass(),
            $resource->formClass(),
            $resource->repositoryClass(),
            FormFactoryInterface::class,
            RequestStack::class,
        ]));
    }

    public function apiResourceSnippet(ResourceName $resource): string
    {
        return $this->render($this->skeletonDirectory . '/ApiResource.tpl.php', [
            'resource' => $resource,
            'provider_class' => self::shortName($resource->providerClass()),
            'processor_class' => self::shortName($resource->processorClass()),
        ]);
    }

    /**
     * Generates a class from a skeleton (core or extension one), skipped when it already exists.
     * The `use_statements` variable is built from the skeleton uses.
     */
    public function generateClassFromSkeleton(Generator $generator, ConsoleStyle $io, string $class, SkeletonTemplate $skeleton): bool
    {
        if (class_exists($class) || interface_exists($class) || trait_exists($class)) {
            $this->writeSkipped($io, $class);

            return false;
        }

        try {
            $generator->generateClass($class, $skeleton->path, [
                ...$skeleton->variables,
                'skeleton_directory' => $this->skeletonDirectory,
                'use_statements' => $this->useStatements($class, $skeleton->uses),
            ]);
        } catch (RuntimeCommandException $exception) {
            $this->writeSkipped($io, $class, $this->skipReason($exception));

            return false;
        }

        return true;
    }

    /**
     * Merges the missing keys into `translations/<domain>.<locale>.yaml` (fr and en), existing keys kept.
     *
     * @param array<string, mixed> $defaults
     */
    public function addTranslations(Generator $generator, ConsoleStyle $io, string $domain, array $defaults): void
    {
        foreach (self::LOCALES as $locale) {
            $this->mergeTranslations($generator, $io, $generator->getRootDirectory() . '/translations/' . $domain . '.' . $locale . '.yaml', $defaults);
        }
    }

    public static function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return false === $position ? $class : substr($class, $position + 1);
    }

    /**
     * The core skeleton `$name`, changed by the enabled extensions.
     *
     * @param array<string, mixed> $variables
     * @param list<string>         $uses
     */
    private function skeleton(string $name, ResourceName $resource, array $variables, array $uses = []): SkeletonTemplate
    {
        $skeleton = new SkeletonTemplate($name, $this->skeletonDirectory . '/' . $name, $variables, $uses);

        foreach ($this->extensions as $extension) {
            $extension->configureTemplate($skeleton, $resource);
        }

        return $skeleton;
    }

    private function generateTemplate(Generator $generator, ConsoleStyle $io, ResourceName $resource, string $template): void
    {
        $target = $resource->templateDirectory() . '/' . $template . '.html.twig';
        $skeleton = $this->skeleton('twig/' . $template . '.tpl.php', $resource, ['resource' => $resource]);

        try {
            $generator->generateTemplate($target, $skeleton->path, [
                ...$skeleton->variables,
                'skeleton_directory' => $this->skeletonDirectory,
            ]);
        } catch (RuntimeCommandException $exception) {
            $this->writeSkipped($io, 'templates/' . $target, $this->skipReason($exception));
        }
    }

    /**
     * @param array<string, mixed> $defaults
     */
    private function mergeTranslations(Generator $generator, ConsoleStyle $io, string $path, array $defaults): void
    {
        $relativePath = ltrim(substr($path, \strlen($generator->getRootDirectory())), '/');
        $content = is_file($path) ? (string) file_get_contents($path) : '';
        $existing = $this->parseTranslations($io, $relativePath, $content);

        if (null === $existing) {
            return;
        }

        $existingKeys = self::flatten($existing);
        $missing = array_diff_key(self::flatten($defaults), $existingKeys);

        if ([] === $missing) {
            $io->text(\sprintf('<fg=yellow>skipped</>: %s (translation keys already present)', $relativePath));

            return;
        }

        $topLevelKeys = array_keys($defaults);
        $isNewBlock = [] === array_filter(
            array_keys($existingKeys),
            static fn (string $key): bool => array_any($topLevelKeys, static fn (string $top): bool => $key === $top || str_starts_with($key, $top . '.')),
        );

        if ($isNewBlock) {
            $separator = '' === $content || str_ends_with($content, "\n") ? '' : "\n";
            $generator->dumpFile($path, $content . $separator . ('' === $content ? '' : "\n") . Yaml::dump($defaults, 10, 4));

            return;
        }

        $io->note(\sprintf('%s is rewritten to merge the missing keys: YAML comments of that file are lost.', $relativePath));
        $generator->dumpFile($path, Yaml::dump(array_replace_recursive($defaults, $existing), 10, 4));
    }

    /**
     * The translations already in the file, null (with a warning) when it cannot be merged into.
     *
     * @return array<mixed>|null
     */
    private function parseTranslations(ConsoleStyle $io, string $relativePath, string $content): ?array
    {
        try {
            $existing = '' === trim($content) ? [] : Yaml::parse($content);
        } catch (ParseException $exception) {
            $io->warning(\sprintf('%s is not valid YAML (%s): translations not added.', $relativePath, $exception->getMessage()));

            return null;
        }

        if (!\is_array($existing)) {
            $io->warning(\sprintf('%s is not a YAML mapping: translations not added.', $relativePath));

            return null;
        }

        return $existing;
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string, mixed>
     */
    private static function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $key = $prefix . $key;

            if (\is_array($value) && [] !== $value) {
                $flat += self::flatten($value, $key . '.');
            } else {
                $flat[$key] = $value;
            }
        }

        return $flat;
    }

    /**
     * @param list<string> $classes
     */
    private function useStatements(string $generatedClass, array $classes): string
    {
        $namespace = substr($generatedClass, 0, (int) strrpos($generatedClass, '\\'));
        $statements = [];

        foreach ($classes as $class) {
            $name = explode(' as ', $class)[0];

            if (substr($name, 0, (int) strrpos($name, '\\')) === $namespace && !str_contains($class, ' as ')) {
                continue;
            }

            $statements[$class] = 'use ' . $class . ';';
        }

        uksort($statements, static fn (string $a, string $b): int => strcasecmp(str_replace('\\', ' ', $a), str_replace('\\', ' ', $b)));

        return implode("\n", $statements) . "\n";
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function render(string $path, array $variables): string
    {
        ob_start();

        try {
            (static function (string $__template, array $__variables): void {
                extract($__variables, \EXTR_SKIP);

                include $__template;
            })($path, [...$variables, 'skeleton_directory' => $this->skeletonDirectory]);

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Null (default "already exists" reason) when the target file is already there, the error message otherwise.
     */
    private function skipReason(RuntimeCommandException $exception): ?string
    {
        return str_contains($exception->getMessage(), self::ALREADY_EXISTS) ? null : $exception->getMessage();
    }

    private function writeSkipped(ConsoleStyle $io, string $what, ?string $reason = null): void
    {
        $io->text(\sprintf('<fg=yellow>skipped</>: %s (%s)', $what, $reason ?? self::ALREADY_EXISTS));
    }
}
