<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Serializer;

use ApiPlatform\Metadata\Exception\OperationNotFoundException;
use ApiPlatform\Metadata\Exception\PropertyNotFoundException;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;

/**
 * Relations written by id: in a resource payload, a relation (to one or to many) given as an
 * id (`"contributor": 12`, `"roles": [3, "4"]`) is turned into the IRI of the target resource,
 * then the payload goes on to the API Platform item denormalizer, which only takes an IRI or a
 * nested document. An IRI or a nested document is left as is. An unknown id fails as an
 * unknown IRI does.
 *
 * The target resource is the one of the property type (the project entity when the bundle
 * one is overridden); its id is the single URI variable of its item GET operation.
 */
final class RelationIdDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    /**
     * Deserialization path of the payload already handled, so the delegated call of the same
     * payload is not handled again (its nested documents are: their path differs).
     */
    private const string HANDLED_PATH = 'gingerminds_core_relation_ids_path';

    public function __construct(
        private readonly ResourceClassResolverInterface $resourceClassResolver,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly PropertyMetadataFactoryInterface $propertyMetadataFactory,
        private readonly IriConverterInterface $iriConverter,
        private readonly ?NameConverterInterface $nameConverter = null,
    ) {
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => false];
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return \is_array($data)
            && !array_is_list($data)
            && ($context[self::HANDLED_PATH] ?? null) !== ($context['deserialization_path'] ?? '')
            && $this->resourceClassResolver->isResourceClass($type);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        \assert(\is_array($data));

        \assert(class_exists($type) || interface_exists($type));

        foreach ($data as $attribute => $value) {
            $class = $this->relationClass($type, (string) $attribute, $format, $context);

            if (null === $class) {
                continue;
            }

            $data[$attribute] = \is_array($value) && array_is_list($value)
                ? array_map(fn (mixed $item): mixed => $this->toIri($item, $class), $value)
                : $this->toIri($value, $class);
        }

        $context[self::HANDLED_PATH] = $context['deserialization_path'] ?? '';

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    /**
     * Resource class targeted by the attribute (one or many), null when it is no relation.
     *
     * @param class-string         $class
     * @param array<string, mixed> $context
     */
    private function relationClass(string $class, string $attribute, ?string $format, array $context): ?string
    {
        $property = $this->nameConverter?->denormalize($attribute, $class, $format, $context) ?? $attribute;

        try {
            $type = $this->propertyMetadataFactory->create($class, $property)->getNativeType();
        } catch (PropertyNotFoundException) {
            return null;
        }

        while ($type instanceof Type && !$type instanceof ObjectType) {
            $type = match (true) {
                $type instanceof CollectionType => $type->getCollectionValueType(),
                $type instanceof WrappingTypeInterface => $type->getWrappedType(),
                default => null,
            };
        }

        if (!$type instanceof ObjectType || !$this->resourceClassResolver->isResourceClass($type->getClassName())) {
            return null;
        }

        return $type->getClassName();
    }

    private function toIri(mixed $id, string $class): mixed
    {
        $item = $this->isId($id) ? $this->itemOperation($class) : null;

        if (null === $item) {
            return $id;
        }

        [$resourceClass, $operation, $variable] = $item;

        return $this->iriConverter->getIriFromResource($resourceClass, UrlGeneratorInterface::ABS_PATH, $operation, [
            'uri_variables' => [$variable => $id],
        ]);
    }

    /**
     * An id, not an IRI nor a URL.
     */
    private function isId(mixed $id): bool
    {
        return \is_int($id) || (\is_string($id) && '' !== $id && !str_starts_with($id, '/') && !str_contains($id, '://'));
    }

    /**
     * The item operation of the resource and its single URI variable, null when it has none or
     * several variables.
     *
     * @return array{class-string, HttpOperation, string}|null
     */
    private function itemOperation(string $class): ?array
    {
        /** @var class-string $resourceClass */
        $resourceClass = $this->resourceClassResolver->getResourceClass(null, $class);

        try {
            $operation = $this->resourceMetadataFactory->create($resourceClass)->getOperation(null, false, true);
        } catch (OperationNotFoundException) {
            return null;
        }

        $variables = $operation instanceof HttpOperation ? array_keys($operation->getUriVariables() ?? []) : [];

        return $operation instanceof HttpOperation && 1 === \count($variables) ? [$resourceClass, $operation, (string) $variables[0]] : null;
    }
}
