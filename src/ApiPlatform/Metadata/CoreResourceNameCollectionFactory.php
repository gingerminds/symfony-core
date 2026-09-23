<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Metadata;

use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;

final readonly class CoreResourceNameCollectionFactory implements ResourceNameCollectionFactoryInterface
{
    /**
     * @param list<class-string> $overriddenEntities
     */
    public function __construct(
        private ResourceNameCollectionFactoryInterface $decorated,
        private array $overriddenEntities,
    ) {
    }

    public function create(): ResourceNameCollection
    {
        $classes = [];

        foreach ($this->decorated->create() as $class) {
            if (!\in_array($class, $this->overriddenEntities, true)) {
                $classes[] = $class;
            }
        }

        return new ResourceNameCollection($classes);
    }
}
