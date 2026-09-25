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

        if (null === $value || 'null' === $value) {
            $this->applyIsNull($query, $owner);

            return;
        }

        $this->applyIn($query, $owner, SelectFilterHandler::normalize($value));
    }

    /**
     * @param array{metadata: ClassMetadata<object>, alias: string, name: string} $owner
     */
    private function applyIsNull(QueryBuilderHelper $query, array $owner): void
    {
        $qb = $query->getQueryBuilder();
        $metadata = $owner['metadata'];
        $name = $owner['name'];

        if ($this->isToMany($metadata, $name)) {
            $qb->andWhere($qb->expr()->isNull($query->join($owner['alias'], $name) . '.id'));
        } elseif ($metadata->hasAssociation($name)) {
            $qb->andWhere($qb->expr()->isNull(\sprintf('IDENTITY(%s.%s)', $owner['alias'], $name)));
        } else {
            $qb->andWhere($qb->expr()->isNull(\sprintf('%s.%s', $owner['alias'], $name)));
        }
    }

    /**
     * @param array{metadata: ClassMetadata<object>, alias: string, name: string} $owner
     * @param list<scalar>                                                        $values
     */
    private function applyIn(QueryBuilderHelper $query, array $owner, array $values): void
    {
        if ([] === $values) {
            return;
        }

        $qb = $query->getQueryBuilder();
        $metadata = $owner['metadata'];
        $name = $owner['name'];

        if (!$metadata->hasAssociation($name)) {
            $qb->andWhere(\sprintf('%s.%s IN (%s)', $owner['alias'], $name, $query->parameter($values)));
        } elseif ($this->isToMany($metadata, $name)) {
            $alias = $query->join($owner['alias'], $name);
            $identifier = $query->getEntityManager()
                ->getClassMetadata($metadata->getAssociationTargetClass($name))
                ->getSingleIdentifierFieldName();

            $qb->andWhere(\sprintf('%s.%s IN (%s)', $alias, $identifier, $query->parameter($values)))
                ->distinct();
        } else {
            $qb->andWhere(\sprintf('IDENTITY(%s.%s) IN (%s)', $owner['alias'], $name, $query->parameter($values)));
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function isToMany(ClassMetadata $metadata, string $name): bool
    {
        return $metadata->hasAssociation($name) && $metadata->isCollectionValuedAssociation($name);
    }
}
