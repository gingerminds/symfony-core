<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Filter;

use ApiPlatform\Metadata\Parameter;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;

/**
 * The search of a list, `search=text`: a case-insensitive `LIKE` on each of the `#[Searchable]`
 * paths (the parameter's `properties`), any of them matching. Unlike API Platform's
 * FreeTextQueryFilter + PartialSearchFilter, a path through an association is LEFT joined: a row
 * without the relation is still found by its other fields.
 */
final class SearchableFilter extends AbstractPropertyFilter
{
    public function getOpenApiParameters(Parameter $parameter): OpenApiParameter
    {
        return new OpenApiParameter(
            name: (string) $parameter->getKey(),
            in: 'query',
            description: 'Searched (case-insensitive, partial) in: ' . implode(', ', $parameter->getProperties() ?? []),
            schema: ['type' => 'string'],
        );
    }

    protected function filter(QueryBuilderHelper $query, string $property, mixed $value, Parameter $parameter): void
    {
        if (!\is_string($value) || '' === trim($value)) {
            return;
        }

        $qb = $query->getQueryBuilder();
        $placeholder = null;
        $conditions = [];

        foreach ($parameter->getProperties() ?? [$property] as $path) {
            $field = $query->resolveField($path);

            if (null === $field) {
                continue;
            }

            $placeholder ??= $query->parameter('%' . addcslashes(mb_strtolower(trim($value)), '%_\\') . '%');
            $conditions[] = \sprintf("LOWER(%s) LIKE %s ESCAPE '\\'", $field, $placeholder);
        }

        if ([] !== $conditions) {
            $qb->andWhere($qb->expr()->orX(...$conditions));
        }
    }
}
