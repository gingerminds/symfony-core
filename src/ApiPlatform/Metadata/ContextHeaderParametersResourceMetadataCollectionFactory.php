<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Metadata;

use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

final readonly class ContextHeaderParametersResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $decorated,
        private HeaderParameterRegistry $registry,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);
        $headerParameters = $this->registry->for($resourceClass);

        if ([] === $headerParameters) {
            return $collection;
        }

        foreach ($collection as $i => $resource) {
            $operations = $resource->getOperations();

            if (!$operations instanceof Operations) {
                continue;
            }

            foreach ($operations as $name => $operation) {
                $parameters = $operation->getParameters() ?? new Parameters();

                foreach ($headerParameters as $headerParameter) {
                    $parameters->add((string) $headerParameter->getKey(), $headerParameter);
                }

                $operations->add($name, $operation->withParameters($parameters));
            }

            $collection[$i] = $resource->withOperations($operations->sort());
        }

        return $collection;
    }
}
