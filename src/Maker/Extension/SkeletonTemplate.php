<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Maker\Extension;

/**
 * A core skeleton about to be rendered, changed by ResourceMakerExtensionInterface::configureTemplate().
 */
final class SkeletonTemplate
{
    /**
     * @param string               $name      skeleton name relative to the core skeleton directory (e.g. `Entity.tpl.php`)
     * @param string               $path      absolute path of the skeleton rendered (replace it to render another one)
     * @param array<string, mixed> $variables variables of the skeleton
     * @param list<string>         $uses      classes imported by the generated class (`Foo\Bar` or `Foo\Bar as Baz`)
     */
    public function __construct(
        public readonly string $name,
        public string $path,
        public array $variables = [],
        public array $uses = [],
    ) {
    }

    public function addUse(string ...$classes): void
    {
        foreach ($classes as $class) {
            if (!\in_array($class, $this->uses, true)) {
                $this->uses[] = $class;
            }
        }
    }

    public function set(string $variable, mixed $value): void
    {
        $this->variables[$variable] = $value;
    }

    /**
     * Appends values to a list variable (e.g. the `interfaces` of Entity.tpl.php).
     */
    public function append(string $variable, mixed ...$values): void
    {
        $current = $this->variables[$variable] ?? [];
        $this->variables[$variable] = [...(\is_array($current) ? array_values($current) : []), ...array_values($values)];
    }

    /**
     * The `use` statements of `$generatedClass` (sorted, classes of its own namespace left out).
     */
    public function useStatements(string $generatedClass): string
    {
        $namespace = substr($generatedClass, 0, (int) strrpos($generatedClass, '\\'));
        $statements = [];

        foreach ($this->uses as $class) {
            $name = explode(' as ', $class)[0];

            if (substr($name, 0, (int) strrpos($name, '\\')) === $namespace && !str_contains($class, ' as ')) {
                continue;
            }

            $statements[$class] = 'use ' . $class . ';';
        }

        uksort($statements, static fn (string $a, string $b): int => strcasecmp(str_replace('\\', ' ', $a), str_replace('\\', ' ', $b)));

        return implode("\n", $statements) . "\n";
    }
}
