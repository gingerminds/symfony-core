<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Resource;

use function is_object;

final class ResourceRegistry
{
    /** @var array<string, ResourceDefinition> */
    private array $definitions = [];

    /**
     * @param array<string, array<string, mixed>> $resources
     */
    public function __construct(array $resources = [])
    {
        foreach ($resources as $name => $config) {
            /* @phpstan-ignore argument.type */
            $this->definitions[$name] = ResourceDefinition::fromArray($name, $config);
        }
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    public function get(string $name): ResourceDefinition
    {
        return $this->definitions[$name] ?? throw new \InvalidArgumentException(\sprintf(
            'Unknown resource "%s". Known resources: "%s".',
            $name,
            implode('", "', array_keys($this->definitions)),
        ));
    }

    /**
     * @return class-string
     */
    public function getEntityClass(string $name): string
    {
        return $this->get($name)->entity;
    }

    public function findByEntity(object|string $entity): ?ResourceDefinition
    {
        $class = is_object($entity) ? $entity::class : $entity;
        $match = null;

        foreach ($this->definitions as $definition) {
            if (is_a($class, $definition->entity, true) && (null === $match || is_a($definition->entity, $match->entity, true))) {
                $match = $definition;
            }
        }

        return $match;
    }

    /**
     * @return array<string, ResourceDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }
}
