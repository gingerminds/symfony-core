<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

/**
 * The admin views of a tree from its nodes, loaded at once and ordered within each level
 * (TreeRepositoryInterface::findTreeNodes()).
 */
final class TreeNodes
{
    /**
     * The key of the roots in groupByParent().
     */
    public const string ROOT = '';

    /**
     * The nodes grouped by parent id, the roots under ROOT.
     *
     * @template T of TreeNodeInterface
     *
     * @param iterable<T> $nodes
     *
     * @return array<int|string, list<T>>
     */
    public static function groupByParent(iterable $nodes): array
    {
        $children = [];

        foreach ($nodes as $node) {
            $children[(string) ($node->getParent()?->getId() ?? self::ROOT)][] = $node;
        }

        return $children;
    }

    /**
     * The tree depth-first, each node with its depth.
     *
     * @template T of TreeNodeInterface
     *
     * @param iterable<T> $nodes
     *
     * @return list<array{T, int}>
     */
    public static function flatten(iterable $nodes): array
    {
        $children = self::groupByParent($nodes);
        $flatten = static function (string $parentId, int $depth) use (&$flatten, $children): iterable {
            foreach ($children[$parentId] ?? [] as $node) {
                yield [$node, $depth];
                yield from $flatten((string) $node->getId(), $depth + 1);
            }
        };

        return iterator_to_array($flatten(self::ROOT, 0), false);
    }

    /**
     * The possible parents of `$node`: every node but itself and its descendants, with their depth
     * (to indent the choice labels).
     *
     * @template T of TreeNodeInterface
     *
     * @param iterable<T> $nodes
     *
     * @return array{list<T>, array<int|string, int>} the choices and their depth by id
     */
    public static function parentChoices(iterable $nodes, ?TreeNodeInterface $node): array
    {
        $choices = [];
        $depths = [];
        $excludedDepth = null;

        foreach (self::flatten($nodes) as [$choice, $depth]) {
            // The descendants of the node follow it, deeper.
            if (null !== $excludedDepth && $depth > $excludedDepth) {
                continue;
            }

            $excludedDepth = null;

            if (null !== $node?->getId() && $choice->getId() === $node->getId()) {
                $excludedDepth = $depth;

                continue;
            }

            $choices[] = $choice;
            $depths[(string) $choice->getId()] = $depth;
        }

        return [$choices, $depths];
    }
}
