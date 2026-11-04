<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Metadata;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Model\EagerLoads;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;

/**
 * A collection association with `#[EagerLoad]` of an entity whose repository is a core one is
 * not fetch-joined by the API Platform eager loading (`fetchEager: false`): the core loads it,
 * fetch-joined in an item read (createListQueryBuilder()) and after the page in a collection
 * (EagerCollectionProvider). Joined into a page, it turns the page and its count into DISTINCT
 * queries over every joined row. An explicit `fetchEager` is kept.
 */
final readonly class EagerCollectionPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    public function __construct(
        private PropertyMetadataFactoryInterface $decorated,
        private ManagerRegistry $registry,
    ) {
    }

    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        $metadata = $this->decorated->create($resourceClass, $property, $options);

        if (null !== $metadata->getFetchEager() || !class_exists($resourceClass) || !$this->isEagerLoadedCollection($resourceClass, $property)) {
            return $metadata;
        }

        return $metadata->withFetchEager(false);
    }

    /**
     * @param class-string $resourceClass
     */
    private function isEagerLoadedCollection(string $resourceClass, string $property): bool
    {
        $manager = $this->registry->getManagerForClass($resourceClass);

        if (!$manager instanceof EntityManagerInterface) {
            return false;
        }

        $classMetadata = $manager->getClassMetadata($resourceClass);
        $repositoryClass = $classMetadata->customRepositoryClassName;

        if (
            null === $repositoryClass
            || !is_a($repositoryClass, RepositoryInterface::class, true)
            || !$classMetadata->hasAssociation($property)
            || !$classMetadata->isCollectionValuedAssociation($property)
        ) {
            return false;
        }

        return array_any(EagerLoads::of($resourceClass), static fn (string $path): bool => explode('.', $path, 2)[0] === $property);
    }
}
