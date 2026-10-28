<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository;

use Doctrine\ORM\QueryBuilder;
use Gingerminds\CoreBundle\Model\TreeNodeInterface;

/**
 * TreeRepositoryInterface for an AbstractRepository of TreeNodeInterface entities (`parent`
 * association, `position` field). The reads start from the list query (its context scope); a new
 * node, or one moved to another parent, goes to the end of its level on save. treeScope() gives
 * the criteria of the tree of a node (`['menu' => $item->getMenu()]`).
 *
 * @phpstan-require-extends AbstractRepository
 */
trait TreeRepositoryTrait
{
    public function findTreeNodes(array $scope = []): array
    {
        $qb = $this->createScopedQueryBuilder();
        $alias = $qb->getRootAliases()[0];
        $this->applyTreeCriteria($qb, $scope);

        /** @var list<TreeNodeInterface> $nodes */
        $nodes = $qb->addOrderBy($alias . '.position')
            ->addOrderBy($alias . '.' . $this->getClassMetadata()->getSingleIdentifierFieldName())
            ->getQuery()
            ->getResult();

        return $nodes;
    }

    public function reorderLevel(int|string|null $parentId, array $ids, array $scope = []): void
    {
        $qb = $this->createScopedQueryBuilder();
        $this->applyTreeCriteria($qb, [...$scope, 'parent' => $parentId]);
        $siblings = [];

        foreach ($qb->getQuery()->getResult() as $node) {
            if ($node instanceof TreeNodeInterface) {
                $siblings[(string) $node->getId()] = $node;
            }
        }

        foreach (array_values($ids) as $position => $id) {
            if (isset($siblings[(string) $id])) {
                $siblings[(string) $id]->setPosition($position);
            }
        }

        $this->getEntityManager()->flush();
    }

    public function findIdsWithDescendants(array $ids, array $scope = []): array
    {
        $qb = $this->createScopedQueryBuilder();
        $alias = $qb->getRootAliases()[0];
        $this->applyTreeCriteria($qb, $scope);
        $children = [];
        $existing = [];

        $rows = $qb->select(\sprintf('%1$s.%2$s AS id, IDENTITY(%1$s.parent) AS parent', $alias, $this->getClassMetadata()->getSingleIdentifierFieldName()))
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            $children[(string) $row['parent']][] = $row['id'];
            $existing[(string) $row['id']] = $row['id'];
        }

        $result = [];
        $pending = array_values(array_intersect_key($existing, array_flip(array_map(strval(...), $ids))));

        while ([] !== $pending) {
            $id = array_pop($pending);

            if (!isset($result[(string) $id])) {
                $result[(string) $id] = $id;
                array_push($pending, ...($children[(string) $id] ?? []));
            }
        }

        return array_values($result);
    }

    /**
     * The criteria of the tree of a node, beyond its parent (none: one tree per entity).
     *
     * @return array<string, mixed>
     */
    protected function treeScope(TreeNodeInterface $node): array
    {
        return [];
    }

    protected function beforeSave(object $entity): void
    {
        parent::beforeSave($entity);

        if ($entity instanceof TreeNodeInterface) {
            $this->placeAtEndOfLevel($entity);
        }
    }

    /**
     * A new node, or one moved to another parent, goes to the end of its level.
     */
    protected function placeAtEndOfLevel(TreeNodeInterface $node): void
    {
        $original = $this->getEntityManager()->getUnitOfWork()->getOriginalEntityData($node);

        if ([] !== $original && ($original['parent'] ?? null) === $node->getParent()) {
            return;
        }

        $qb = $this->createQueryBuilder('n')->select('MAX(n.position)');
        $this->applyTreeCriteria($qb, [...$this->treeScope($node), 'parent' => $node->getParent()]);

        if (null !== $node->getId()) {
            $qb->andWhere(\sprintf('n.%s <> :gm_tree_id', $this->getClassMetadata()->getSingleIdentifierFieldName()))
                ->setParameter('gm_tree_id', $node->getId());
        }

        $max = $qb->getQuery()->getSingleScalarResult();
        $node->setPosition(null === $max ? 0 : (int) $max + 1);
    }

    /**
     * @param array<string, mixed> $criteria field => value, null for IS NULL
     */
    private function applyTreeCriteria(QueryBuilder $qb, array $criteria): void
    {
        $alias = $qb->getRootAliases()[0];

        foreach ($criteria as $field => $value) {
            if (null === $value) {
                $qb->andWhere(\sprintf('%s.%s IS NULL', $alias, $field));

                continue;
            }

            $qb->andWhere(\sprintf('%s.%s = :gm_tree_%s', $alias, $field, $field))
                ->setParameter('gm_tree_' . $field, $value);
        }
    }
}
