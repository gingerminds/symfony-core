<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Merges the missing keys into the `translations/<domain>.<locale>.yaml` files of the app, existing keys kept.
 */
final readonly class TranslationMerger
{
    private const array LOCALES = ['fr', 'en'];

    /**
     * @param array<string, mixed> $defaults
     */
    public function merge(Generator $generator, ConsoleStyle $io, string $domain, array $defaults): void
    {
        foreach (self::LOCALES as $locale) {
            $this->mergeFile($generator, $io, $generator->getRootDirectory() . '/translations/' . $domain . '.' . $locale . '.yaml', $defaults);
        }
    }

    /**
     * @param array<string, mixed> $defaults
     */
    private function mergeFile(Generator $generator, ConsoleStyle $io, string $path, array $defaults): void
    {
        $relativePath = ltrim(substr($path, \strlen($generator->getRootDirectory())), '/');
        $content = is_file($path) ? (string) file_get_contents($path) : '';
        $existing = $this->parse($io, $relativePath, $content);

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
    private function parse(ConsoleStyle $io, string $relativePath, string $content): ?array
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
}
