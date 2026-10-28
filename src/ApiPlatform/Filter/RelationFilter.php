<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Filter;

use ApiPlatform\Metadata\Parameter;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;

/**
 * The related ids of an association, not IRIs (the API Platform IriFilter): `category=5`,
 * `tags[]=3&tags[]=4` (any of them), `category=null` for the rows without relation (combinable:
 * `category[]=null&category[]=5`).
 *
 * A to-many association is matched through a subquery: the list query does not join the
 * collection, its rows are not duplicated and its pagination stays a plain LIMIT.
 */
final class RelationFilter extends AbstractPropertyFilter
{
    public const string NULL = 'null';

    /**
     * @return list<OpenApiParameter>
     */
    public function getOpenApiParameters(Parameter $parameter): array
    {
        return self::listOpenApiParameters($parameter, 'A related id, a list of them, or `null` for none');
    }

    protected function filter(QueryBuilderHelper $query, string $property, mixed $value, Parameter $parameter): void
    {
        $owner = $query->resolveOwner($property);
        $values = self::values($value);

        if (null === $owner || [] === $values || !$owner['metadata']->hasAssociation($owner['name'])) {
            return;
        }

        $ids = array_values(array_filter($values, static fn (mixed $item): bool => self::NULL !== $item));
        $metadata = $owner['metadata'];
        $field = $owner['alias'] . '.' . $owner['name'];
        $qb = $query->getQueryBuilder();
        $conditions = [];

        if (\count($ids) < \count($values)) {
            $conditions[] = $metadata->isCollectionValuedAssociation($owner['name']) ? $field . ' IS EMPTY' : $field . ' IS NULL';
        }

        if ([] !== $ids && $metadata->isCollectionValuedAssociation($owner['name'])) {
            $entityManager = $query->getEntityManager();
            $target = $entityManager->getClassMetadata($metadata->getAssociationTargetClass($owner['name']));
            $sub = $query->uniqueAlias($owner['name'] . '_owner');
            $related = $query->uniqueAlias($owner['name'] . '_related');
            $conditions[] = \sprintf(
                '%1$s.%2$s IN (SELECT %3$s.%2$s FROM %4$s %3$s JOIN %3$s.%5$s %6$s WHERE %6$s.%7$s IN (%8$s))',
                $owner['alias'],
                $metadata->getSingleIdentifierFieldName(),
                $sub,
                $metadata->getName(),
                $owner['name'],
                $related,
                $target->getSingleIdentifierFieldName(),
                $query->parameter($ids),
            );
        } elseif ([] !== $ids) {
            $conditions[] = \sprintf('IDENTITY(%s) IN (%s)', $field, $query->parameter($ids));
        }

        $qb->andWhere($qb->expr()->orX(...$conditions));
    }
}
