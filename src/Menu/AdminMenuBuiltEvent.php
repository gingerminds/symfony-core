<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Menu;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once the entries of every provider are collected and their sections merged,
 * before the sort by weight and the permission filter: an entry added or replaced here is
 * sorted and filtered like the others. Entries are found by id, at any level.
 */
final class AdminMenuBuiltEvent extends Event
{
    /**
     * @param list<MenuItem> $items
     */
    public function __construct(
        private array $items,
    ) {
    }

    /**
     * @return list<MenuItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function get(string $id): ?MenuItem
    {
        return self::find($this->items, $id);
    }

    /**
     * Adds an entry at the root, or among the children of the entry $parent.
     *
     * @throws \InvalidArgumentException when there is no entry $parent
     */
    public function add(MenuItem $item, ?string $parent = null): void
    {
        if (null === $parent) {
            $this->items[] = $item;

            return;
        }

        $section = $this->get($parent) ?? throw new \InvalidArgumentException(\sprintf('The admin menu has no entry "%s".', $parent));
        $section->children[] = $item;
    }

    /**
     * @return bool false when there is no entry $id
     */
    public function remove(string $id): bool
    {
        return self::walk($this->items, $id, static fn (): null => null);
    }

    /**
     * @param MenuItem|\Closure(MenuItem): MenuItem $item the new entry, or a function returning it from the current one
     *
     * @return bool false when there is no entry $id
     */
    public function replace(string $id, MenuItem|\Closure $item): bool
    {
        return self::walk($this->items, $id, $item instanceof MenuItem ? static fn (): MenuItem => $item : $item);
    }

    /**
     * @param list<MenuItem> $items
     */
    private static function find(array $items, string $id): ?MenuItem
    {
        foreach ($items as $item) {
            if ($id === $item->id) {
                return $item;
            }

            if (($found = self::find($item->children, $id)) instanceof MenuItem) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Replaces the first entry $id by what $replace returns from it, removes it on null.
     *
     * @param list<MenuItem>                $items
     * @param \Closure(MenuItem): ?MenuItem $replace
     */
    private static function walk(array &$items, string $id, \Closure $replace): bool
    {
        foreach ($items as $index => $item) {
            if ($id === $item->id) {
                $replacement = $replace($item);

                if (null === $replacement) {
                    array_splice($items, $index, 1);
                } else {
                    $items[$index] = $replacement;
                }

                return true;
            }

            $children = $item->children;

            if (self::walk($children, $id, $replace)) {
                $item->children = $children;

                return true;
            }
        }

        return false;
    }
}
