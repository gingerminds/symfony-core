<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository;

use Gingerminds\CoreBundle\Model\TreeNodeInterface;

/**
 * The repository of the nodes of an admin tree (TreeRepositoryTrait). `$scope` criteria
 * restrict a tree, e.g. `['menu' => $menu]`: the items of one menu.
 */
interface TreeRepositoryInterface
{
    /**
     * Every node of the tree, ordered within each level (position, then id).
     *
     * @param array<string, mixed> $scope
     *
     * @return list<TreeNodeInterface>
     */
    public function findTreeNodes(array $scope = []): array;

    /**
     * Applies the order of one level (`$parentId` null: the roots); the ids of other levels or
     * trees are ignored.
     *
     * @param list<int|string>     $ids
     * @param array<string, mixed> $scope
     */
    public function reorderLevel(int|string|null $parentId, array $ids, array $scope = []): void;

    /**
     * These nodes and all their descendants.
     *
     * @param list<int|string>     $ids
     * @param array<string, mixed> $scope
     *
     * @return list<int|string>
     */
    public function findIdsWithDescendants(array $ids, array $scope = []): array;
}
