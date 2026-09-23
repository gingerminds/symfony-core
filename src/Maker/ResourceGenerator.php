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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class ResourceGenerator
{
    private const array FIELD_NAME_LABELS = ['fr' => 'Nom', 'en' => 'Name'];

    private string $skeletonDirectory;

    public function __construct(?string $skeletonDirectory = null)
    {
        $this->skeletonDirectory = $skeletonDirectory ?? __DIR__ . '/skeleton';
    }

    public function generateEntity(Generator $generator, ConsoleStyle $io, ResourceName $resource, bool $api): bool
    {
        $uses = [
            'Doctrine\\ORM\\Mapping as ORM',
            'Symfony\\Component\\Validator\\Constraints as Assert',
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

        return $this->generateClass($generator, $io, $resource->entityClass(), 'Entity.tpl.php', [
            'use_statements' => $this->useStatements($resource->entityClass(), $uses),
            'resource' => $resource,
            'api' => $api,
            'repository_class' => self::shortName($resource->repositoryClass()),
            'provider_class' => self::shortName($resource->providerClass()),
            'processor_class' => self::shortName($resource->processorClass()),
        ]);
    }

    public function generateRepository(Generator $generator, ConsoleStyle $io, ResourceName $resource): bool
    {
        return $this->generateClass($generator, $io, $resource->repositoryClass(), 'Repository.tpl.php', [
            'use_statements' => $this->useStatements($resource->repositoryClass(), [
                ManagerRegistry::class,
                AbstractRepository::class,
                $resource->entityClass(),
            ]),
            'entity_class' => self::shortName($resource->entityClass()),
        ]);
    }

    public function generateForm(Generator $generator, ConsoleStyle $io, ResourceName $resource): bool
    {
        return $this->generateClass($generator, $io, $resource->formClass(), 'FormType.tpl.php', [
            'use_statements' => $this->useStatements($resource->formClass(), [
                AbstractType::class,
                TextType::class,
                FormBuilderInterface::class,
                OptionsResolver::class,
                $resource->entityClass(),
            ]),
            'resource' => $resource,
            'entity_class' => self::shortName($resource->entityClass()),
        ]);
    }

    /**
     * Controller, admin templates and admin translations.
     */
    public function generateCrudController(Generator $generator, ConsoleStyle $io, ResourceName $resource): void
    {
        $this->generateClass($generator, $io, $resource->controllerClass(), 'CrudController.tpl.php', [
            'use_statements' => $this->useStatements($resource->controllerClass(), [
                AbstractCrudController::class,
                AsCrudController::class,
                $resource->entityClass(),
                $resource->formClass(),
            ]),
            'resource' => $resource,
            'entity_class' => self::shortName($resource->entityClass()),
            'form_class' => self::shortName($resource->formClass()),
        ]);

        foreach (['index', 'new', 'edit', '_form'] as $template) {
            $this->generateTemplate($generator, $io, $resource, $template);
        }

        foreach (self::FIELD_NAME_LABELS as $locale => $fieldNameLabel) {
            $this->mergeTranslations($generator, $io, $generator->getRootDirectory() . '/translations/admin.' . $locale . '.yaml', [
                $resource->snake => [
                    'name_s' => $resource->label(),
                    'name_p' => $resource->label(true),
                    'field' => ['name' => $fieldNameLabel],
                ],
            ]);
        }
    }

    public function generateVoter(Generator $generator, ConsoleStyle $io, ResourceName $resource): bool
    {
        return $this->generateClass($generator, $io, $resource->voterClass(), 'Voter.tpl.php', [
            'use_statements' => $this->useStatements($resource->voterClass(), [
                AbstractResourceVoter::class,
                $resource->entityClass(),
            ]),
            'resource' => $resource,
            'entity_class' => self::shortName($resource->entityClass()),
        ]);
    }

    /**
     * API Platform state provider and processor.
     */
    public function generateApi(Generator $generator, ConsoleStyle $io, ResourceName $resource): void
    {
        $this->generateClass($generator, $io, $resource->providerClass(), 'Provider.tpl.php', [
            'use_statements' => $this->useStatements($resource->providerClass(), [
                ResourceProvider::class,
                $resource->entityClass(),
                $resource->repositoryClass(),
                RequestStack::class,
            ]),
            'entity_class' => self::shortName($resource->entityClass()),
            'repository_class' => self::shortName($resource->repositoryClass()),
        ]);

        $this->generateClass($generator, $io, $resource->processorClass(), 'Processor.tpl.php', [
            'use_statements' => $this->useStatements($resource->processorClass(), [
                ResourceProcessor::class,
                $resource->entityClass(),
                $resource->formClass(),
                $resource->repositoryClass(),
                FormFactoryInterface::class,
                RequestStack::class,
            ]),
            'entity_class' => self::shortName($resource->entityClass()),
            'repository_class' => self::shortName($resource->repositoryClass()),
            'form_class' => self::shortName($resource->formClass()),
        ]);
    }

    public function apiResourceSnippet(ResourceName $resource): string
    {
        return $this->render('ApiResource.tpl.php', [
            'resource' => $resource,
            'provider_class' => self::shortName($resource->providerClass()),
            'processor_class' => self::shortName($resource->processorClass()),
        ]);
    }

    public static function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return false === $position ? $class : substr($class, $position + 1);
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function generateClass(Generator $generator, ConsoleStyle $io, string $class, string $template, array $variables): bool
    {
        if (class_exists($class) || interface_exists($class) || trait_exists($class)) {
            $this->writeSkipped($io, $class);

            return false;
        }

        try {
            $generator->generateClass($class, $this->skeletonDirectory . '/' . $template, $variables);
        } catch (RuntimeCommandException $exception) {
            $this->writeSkipped($io, $class, str_contains($exception->getMessage(), 'already exists') ? null : $exception->getMessage());

            return false;
        }

        return true;
    }

    private function generateTemplate(Generator $generator, ConsoleStyle $io, ResourceName $resource, string $template): void
    {
        $target = $resource->templateDirectory() . '/' . $template . '.html.twig';

        try {
            $generator->generateTemplate($target, $this->skeletonDirectory . '/twig/' . $template . '.tpl.php', [
                'resource' => $resource,
            ]);
        } catch (RuntimeCommandException $exception) {
            $this->writeSkipped($io, 'templates/' . $target, str_contains($exception->getMessage(), 'already exists') ? null : $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $defaults
     */
    private function mergeTranslations(Generator $generator, ConsoleStyle $io, string $path, array $defaults): void
    {
        $relativePath = ltrim(substr($path, \strlen($generator->getRootDirectory())), '/');
        $content = is_file($path) ? (string) file_get_contents($path) : '';

        try {
            $existing = '' === trim($content) ? [] : Yaml::parse($content);
        } catch (ParseException $exception) {
            $io->warning(\sprintf('%s is not valid YAML (%s): translations not added.', $relativePath, $exception->getMessage()));

            return;
        }

        if (!\is_array($existing)) {
            $io->warning(\sprintf('%s is not a YAML mapping: translations not added.', $relativePath));

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
    private function render(string $template, array $variables): string
    {
        ob_start();

        try {
            (static function (string $__template, array $__variables): void {
                extract($__variables, \EXTR_SKIP);

                include $__template;
            })($this->skeletonDirectory . '/' . $template, $variables);

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    private function writeSkipped(ConsoleStyle $io, string $what, ?string $reason = null): void
    {
        $io->text(\sprintf('<fg=yellow>skipped</>: %s (%s)', $what, $reason ?? 'already exists'));
    }
}
