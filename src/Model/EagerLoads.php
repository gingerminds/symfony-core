<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

/**
 * The association paths an entity eager loads, read from its `#[EagerLoad]` properties
 * (parents and traits included).
 */
final class EagerLoads
{
    /**
     * @param class-string $class
     *
     * @return list<string>
     */
    public static function of(string $class): array
    {
        return PropertyPaths::of($class, EagerLoad::class);
    }
}
