<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Filter\Handler;

use Doctrine\ORM\Mapping\ClassMetadata;
use Gingerminds\CoreBundle\Repository\Filter\FilterHandlerInterface;
use Gingerminds\CoreBundle\Repository\Query\QueryBuilderHelper;

final class SelectEntityFilterHandler implements FilterHandlerInterface
{
    public function apply(QueryBuilderHelper $query, string $property, mixed $value, array $config): void
    {
        $owner = $query->resolveOwner($property);

        if (null === $owner || 'all' === $value || '' === $value || [] === $value) {
            return;
        }

        $qb = $query->getQueryBuilder();
        $metadata = $owner['metadata'];
        $name = $owner['name'];

        if (null === $value || 'null' === $value) {
            if ($this->isToMany($metadata, $name)) {
                $qb->andWhere($qb->expr()->isNull($query->join($owner['alias'], $name) . '.id'));
            } elseif ($metadata->hasAssociation($name)) {
                $qb->andWhere($qb->expr()->isNull(\sprintf('IDENTITY(%s.%s)', $owner['alias'], $name)));
            } else {
                $qb->andWhere($qb->expr()->isNull(\sprintf('%s.%s', $owner['alias'], $name)));
            }

            return;
        }

        $values = SelectFilterHandler::normalize($value);

        if ([] === $values) {
            return;
        }

        if (!$metadata->hasAssociation($name)) {
            $qb->andWhere(\sprintf('%s.%s IN (%s)', $owner['alias'], $name, $query->parameter($values)));

            return;
        }

        if ($this->isToMany($metadata, $name)) {
            $alias = $query->join($owner['alias'], $name);
            $identifier = $query->getEntityManager()
                ->getClassMetadata($metadata->getAssociationTargetClass($name))
                ->getSingleIdentifierFieldName();

            $qb->andWhere(\sprintf('%s.%s IN (%s)', $alias, $identifier, $query->parameter($values)))
                ->distinct();

            return;
        }

        $qb->andWhere(\sprintf('IDENTITY(%s.%s) IN (%s)', $owner['alias'], $name, $query->parameter($values)));
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function isToMany(ClassMetadata $metadata, string $name): bool
    {
        return $metadata->hasAssociation($name) && $metadata->isCollectionValuedAssociation($name);
    }
}
