<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Twig;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Resource\ResourceDefinition;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Twig\Attribute\AsTwigFunction;

/**
 * Twig functions on the resources (`gm_resource()`, `gm_resource_for()`, `gm_entity_choices()`):
 * a Twig runtime, instantiated on the first call only.
 */
final class ResourceExtension
{
    private const int MAX_LOCAL_CHOICES = 500;

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly ManagerRegistry $doctrine,
    ) {
    }

    #[AsTwigFunction('gm_resource')]
    public function resource(string $name): ResourceDefinition
    {
        return $this->resources->get($name);
    }

    #[AsTwigFunction('gm_resource_for')]
    public function resourceFor(string $entityClass): ?ResourceDefinition
    {
        return $this->resources->findByEntity($entityClass);
    }

    /**
     * @param list<int|string>|int|string|null $ids
     *
     * @return list<array{value: string, text: string}>
     */
    #[AsTwigFunction('gm_entity_choices')]
    public function entityChoices(string $entityClass, array|int|string|null $ids = null): array
    {
        if (!class_exists($entityClass)) {
            return [];
        }

        $repository = $this->doctrine->getRepository($entityClass);

        if (null !== $ids) {
            $ids = array_values(array_filter((array) $ids, static fn (mixed $id): bool => '' !== $id && 'all' !== $id));

            if ([] === $ids) {
                return [];
            }

            $entities = $repository->findBy(['id' => $ids]);
        } else {
            $entities = $repository->findBy([], null, self::MAX_LOCAL_CHOICES);
        }

        $choices = [];

        foreach ($entities as $entity) {
            $id = method_exists($entity, 'getId') ? (string) $entity->getId() : '';
            $choices[] = ['value' => $id, 'text' => $entity instanceof \Stringable ? (string) $entity : '#' . $id];
        }

        return $choices;
    }
}
