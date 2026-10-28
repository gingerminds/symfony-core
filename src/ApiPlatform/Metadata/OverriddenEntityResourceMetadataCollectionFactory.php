<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Metadata;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

/**
 * A project entity overriding a bundle entity without `#[ApiResource]` of its own gets the
 * bundle one: the resources read from the bundle class, set on the project class. Decorates
 * the attributes reading, below every other metadata factory (filters, parameters, Doctrine
 * state options...), which then apply to the project class.
 */
final readonly class OverriddenEntityResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    /**
     * @var array<class-string, class-string> bundle entity by project entity
     */
    private array $defaultEntities;

    /**
     * @param array<class-string, class-string> $overriddenEntities project entity by bundle entity
     */
    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $decorated,
        array $overriddenEntities,
    ) {
        $this->defaultEntities = array_flip($overriddenEntities);
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);
        $defaultEntity = $this->defaultEntities[$resourceClass] ?? null;

        // Declared by the project entity: replaces the bundle declaration.
        if (null === $defaultEntity || 0 !== \count($collection)) {
            return $collection;
        }

        /** @var class-string $resourceClass a project entity */
        $inherited = new ResourceMetadataCollection($resourceClass);

        foreach ($this->decorated->create($defaultEntity) as $resource) {
            $inherited[] = $this->withClass($resource, $resourceClass);
        }

        return $inherited;
    }

    /**
     * @param class-string $class
     */
    private function withClass(ApiResource $resource, string $class): ApiResource
    {
        $resource = $resource->withClass($class);
        $operations = [];

        foreach ($resource->getOperations() ?? [] as $name => $operation) {
            $operations[$name] = $operation->withClass($class);
        }

        if ([] !== $operations) {
            $resource = $resource->withOperations(new Operations($operations));
        }

        if (null !== $graphQlOperations = $resource->getGraphQlOperations()) {
            return $resource->withGraphQlOperations(array_map(static fn (object $operation): object => $operation->withClass($class), $graphQlOperations));
        }

        return $resource;
    }
}
