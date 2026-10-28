<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use ApiPlatform\State\ParameterNotFound;
use Doctrine\ORM\QueryBuilder;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;

/**
 * A core API Platform filter (QueryParameter only) on the property of its parameter, a path
 * through to-one or to-many associations joined with LEFT JOIN (`category.name`): a row
 * without the relation is not dropped by the join itself.
 */
abstract class AbstractPropertyFilter implements FilterInterface, OpenApiParameterFilterInterface
{
    use BackwardCompatibleFilterDescriptionTrait;

    /**
     * @param array<string, mixed> $context
     */
    final public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $parameter = $context['parameter'] ?? null;

        if (!$parameter instanceof Parameter) {
            return;
        }

        $value = $parameter->getValue(null);

        if (null === $value || $value instanceof ParameterNotFound) {
            return;
        }

        $query = new QueryBuilderHelper($queryBuilder, $queryBuilder->getRootAliases()[0], $queryBuilder->getEntityManager());
        $this->filter($query, $this->property($parameter), $value, $parameter);
    }

    public function getOpenApiParameters(Parameter $parameter): OpenApiParameter|array|null
    {
        return new OpenApiParameter(name: (string) $parameter->getKey(), in: 'query');
    }

    abstract protected function filter(QueryBuilderHelper $query, string $property, mixed $value, Parameter $parameter): void;

    /**
     * The filtered property path: the one before the API Platform name conversion if any.
     */
    protected function property(Parameter $parameter): string
    {
        $property = $parameter->getExtraProperties()['query_property'] ?? $parameter->getProperty() ?? $parameter->getKey();

        return \is_string($property) ? $property : '';
    }

    /**
     * A value or a list of values, without the empty ones.
     *
     * @return list<scalar>
     */
    protected static function values(mixed $value): array
    {
        return array_values(array_filter(
            \is_array($value) ? $value : [$value],
            static fn (mixed $item): bool => \is_scalar($item) && '' !== $item,
        ));
    }

    /**
     * `key` and `key[]`.
     *
     * @return list<OpenApiParameter>
     */
    protected static function listOpenApiParameters(Parameter $parameter, string $description): array
    {
        $key = (string) $parameter->getKey();

        return [
            new OpenApiParameter(name: $key, in: 'query', description: $description, schema: ['type' => 'string']),
            new OpenApiParameter(
                name: $key . '[]',
                in: 'query',
                description: $description,
                schema: ['type' => 'array', 'items' => ['type' => 'string']],
                style: 'deepObject',
                explode: true,
            ),
        ];
    }
}
