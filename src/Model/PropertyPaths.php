<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

/**
 * The paths declared by a property attribute carrying `paths` (`#[Sortable]`, `#[Searchable]`)
 * on an entity, parent classes and traits included: the property name, or
 * `property.path` for each of its `paths`.
 *
 * @internal
 */
final class PropertyPaths
{
    /** @var array<string, list<string>> */
    private static array $paths = [];

    /**
     * @param class-string                      $class
     * @param class-string<Sortable|Searchable> $attribute
     *
     * @return list<string>
     */
    public static function of(string $class, string $attribute): array
    {
        $key = $attribute . '@' . $class;

        if (isset(self::$paths[$key])) {
            return self::$paths[$key];
        }

        $paths = [];

        for ($reflection = new \ReflectionClass($class); false !== $reflection; $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                foreach ($property->getAttributes($attribute) as $instance) {
                    $declared = $instance->newInstance()->paths;
                    $name = $property->getName();

                    array_push($paths, ...([] === $declared ? [$name] : array_map(static fn (string $path): string => $name . '.' . $path, $declared)));
                }
            }
        }

        return self::$paths[$key] = array_values(array_unique($paths));
    }
}
