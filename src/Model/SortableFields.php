<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

/**
 * The paths an entity may be sorted on, read from its `#[Sortable]` properties (parents included).
 */
final class SortableFields
{
    /** @var array<class-string, list<string>> */
    private static array $paths = [];

    /**
     * @param class-string $class
     *
     * @return list<string>
     */
    public static function of(string $class): array
    {
        if (isset(self::$paths[$class])) {
            return self::$paths[$class];
        }

        $paths = [];

        for ($reflection = new \ReflectionClass($class); false !== $reflection; $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                foreach ($property->getAttributes(Sortable::class) as $attribute) {
                    $sortable = $attribute->newInstance();
                    $name = $property->getName();

                    array_push($paths, ...([] === $sortable->paths ? [$name] : array_map(static fn (string $path): string => $name . '.' . $path, $sortable->paths)));
                }
            }
        }

        return self::$paths[$class] = array_values(array_unique($paths));
    }

    /**
     * @param class-string $class
     */
    public static function allows(string $class, string $path): bool
    {
        return \in_array($path, self::of($class), true);
    }
}
