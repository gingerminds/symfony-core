<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Metadata;

use ApiPlatform\Metadata\HeaderParameter;

final class HeaderParameterRegistry
{
    /** @var array<class-string, HeaderParameter> */
    private array $parameters = [];

    /**
     * @param iterable<HeaderParameterProviderInterface> $providers
     */
    public function __construct(iterable $providers = [])
    {
        foreach ($providers as $provider) {
            $this->register($provider->getMarker(), $provider->getHeaderParameter());
        }
    }

    /**
     * @param class-string $marker
     */
    public function register(string $marker, HeaderParameter $parameter): void
    {
        $this->parameters[$marker] = $parameter;
    }

    /**
     * @return list<HeaderParameter>
     */
    public function for(string $resourceClass): array
    {
        if (!class_exists($resourceClass)) {
            return [];
        }

        $traits = $this->classUsesRecursive($resourceClass);
        $matched = [];

        foreach ($this->parameters as $marker => $parameter) {
            if (isset($traits[$marker]) || (interface_exists($marker) && is_a($resourceClass, $marker, true))) {
                $matched[] = $parameter;
            }
        }

        return $matched;
    }

    /**
     * @param class-string $class
     *
     * @return array<string, string>
     */
    private function classUsesRecursive(string $class): array
    {
        $traits = [];

        foreach ([$class, ...array_values(class_parents($class) ?: [])] as $item) {
            foreach (self::traitUsesRecursive($item) as $trait) {
                $traits[$trait] = $trait;
            }
        }

        return $traits;
    }

    /**
     * @return list<string>
     */
    private static function traitUsesRecursive(string $class): array
    {
        $traits = array_values(class_uses($class) ?: []);

        foreach ($traits as $trait) {
            array_push($traits, ...self::traitUsesRecursive($trait));
        }

        return $traits;
    }
}
