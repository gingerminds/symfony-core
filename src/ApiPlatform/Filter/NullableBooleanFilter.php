<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Filter;

use ApiPlatform\Metadata\Parameter;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;

/**
 * `active=true|1` or `active=false|0`; `false` also matches NULL (a nullable boolean left empty
 * is a "no"), which the API Platform BooleanFilter does not. Any other value is ignored.
 */
final class NullableBooleanFilter extends AbstractPropertyFilter
{
    public function getOpenApiParameters(Parameter $parameter): OpenApiParameter
    {
        return new OpenApiParameter(
            name: (string) $parameter->getKey(),
            in: 'query',
            description: 'true or false (false includes the empty values)',
            schema: ['type' => 'boolean'],
        );
    }

    protected function filter(QueryBuilderHelper $query, string $property, mixed $value, Parameter $parameter): void
    {
        $field = $query->resolveField($property);
        $value = \is_scalar($value) ? strtolower((string) $value) : null;

        if (null === $field || !\in_array($value, ['true', '1', 'false', '0'], true)) {
            return;
        }

        $qb = $query->getQueryBuilder();

        if (\in_array($value, ['true', '1'], true)) {
            $qb->andWhere(\sprintf('%s = %s', $field, $query->parameter(true)));

            return;
        }

        $qb->andWhere($qb->expr()->orX(
            \sprintf('%s = %s', $field, $query->parameter(false)),
            $qb->expr()->isNull($field),
        ));
    }
}
