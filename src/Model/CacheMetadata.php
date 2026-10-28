<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

/**
 * The `#[Cacheable]` and `#[CacheCascade]` of a class: its own, else its closest parent's (an
 * entity overriding a bundle one, a Doctrine proxy).
 */
final class CacheMetadata
{
    /** @var array<string, Cacheable|CacheCascade|null> */
    private static array $attributes = [];

    /**
     * @param class-string $class
     */
    public static function cacheable(string $class): ?Cacheable
    {
        $attribute = self::find($class, Cacheable::class);

        return $attribute instanceof Cacheable ? $attribute : null;
    }

    /**
     * @param class-string $class
     *
     * @return list<string>
     */
    public static function cascadeKeys(string $class): array
    {
        $attribute = self::find($class, CacheCascade::class);

        return $attribute instanceof CacheCascade ? $attribute->keys : [];
    }

    /**
     * @param class-string                         $class
     * @param class-string<Cacheable|CacheCascade> $attribute
     */
    private static function find(string $class, string $attribute): Cacheable|CacheCascade|null
    {
        $key = $attribute . '@' . $class;

        if (\array_key_exists($key, self::$attributes)) {
            return self::$attributes[$key];
        }

        for ($reflection = new \ReflectionClass($class); false !== $reflection; $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getAttributes($attribute) as $instance) {
                return self::$attributes[$key] = $instance->newInstance();
            }
        }

        return self::$attributes[$key] = null;
    }
}
