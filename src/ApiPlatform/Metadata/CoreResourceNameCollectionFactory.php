<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Metadata;

use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;

/**
 * An overridden bundle entity is replaced by its project entity (which gets the bundle
 * `#[ApiResource]`, see OverriddenEntityResourceMetadataCollectionFactory); the resources
 * configured with `api: false` are removed.
 */
final readonly class CoreResourceNameCollectionFactory implements ResourceNameCollectionFactoryInterface
{
    /**
     * @param array<class-string, class-string> $overriddenEntities project entity by bundle entity
     * @param list<class-string>                $disabledResources
     */
    public function __construct(
        private ResourceNameCollectionFactoryInterface $decorated,
        private array $overriddenEntities,
        private array $disabledResources,
    ) {
    }

    public function create(): ResourceNameCollection
    {
        $classes = [];

        foreach ($this->decorated->create() as $class) {
            $classes[] = $this->overriddenEntities[$class] ?? $class;
        }

        $classes = array_values(array_unique($classes));

        return new ResourceNameCollection(array_values(array_diff($classes, $this->disabledResources)));
    }
}
