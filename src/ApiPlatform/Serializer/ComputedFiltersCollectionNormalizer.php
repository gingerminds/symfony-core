<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Serializer;

use ApiPlatform\Metadata\CollectionOperationInterface;
use Gingerminds\CoreBundle\ApiPlatform\Filter\ComputedFilterStore;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Adds the computed filters of the collection (facets: bounds, counts) stored by its provider in
 * ComputedFilterStore to the Hydra collection, as `filters`: decorates the Hydra collection
 * normalizer, the response is serialized once.
 */
final readonly class ComputedFiltersCollectionNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    public const string KEY = 'filters';

    public function __construct(
        private NormalizerInterface $decorated,
        private ComputedFilterStore $store,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $normalized = $this->decorated->normalize($data, $format, $context);
        $resourceClass = $context['resource_class'] ?? null;

        if (
            !\is_array($normalized)
            || !\is_string($resourceClass)
            || !($context['operation'] ?? null) instanceof CollectionOperationInterface
            || !$this->store->has($resourceClass)
        ) {
            return $normalized;
        }

        $normalized[self::KEY] = array_filter(
            $this->store->get($resourceClass),
            static fn (array $filter): bool => [] !== $filter['options'],
        );

        return $normalized;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->decorated->getSupportedTypes($format);
    }

    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        if ($this->decorated instanceof NormalizerAwareInterface) {
            $this->decorated->setNormalizer($normalizer);
        }
    }
}
