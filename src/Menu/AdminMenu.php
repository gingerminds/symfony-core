<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Menu;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class AdminMenu
{
    /** @var list<MenuItem>|null */
    private ?array $items = null;

    /**
     * @param iterable<AdminMenuProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * @return list<MenuItem>
     */
    public function getItems(): array
    {
        if (null !== $this->items) {
            return $this->items;
        }

        $items = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->getItems() as $item) {
                $items[] = $item;
            }
        }

        return $this->items = $this->filter($items);
    }

    /**
     * Merges the sections sharing an id, sorts by weight (stable: registration order on a tie),
     * drops the entries not granted and the empty sections, at every level.
     *
     * @param list<MenuItem> $items
     *
     * @return list<MenuItem>
     */
    private function filter(array $items): array
    {
        $items = $this->merge($items);
        usort($items, static fn (MenuItem $a, MenuItem $b): int => $a->weight <=> $b->weight);
        $visible = [];

        foreach ($items as $item) {
            if (null !== $item->permission && !$this->authorizationChecker->isGranted($item->permission, $item->permissionSubject)) {
                continue;
            }

            $item->children = $this->filter($item->children);

            if (null === $item->route && [] === $item->children) {
                continue;
            }

            $visible[] = $item;
        }

        return $visible;
    }

    /**
     * @param list<MenuItem> $items
     *
     * @return list<MenuItem>
     */
    private function merge(array $items): array
    {
        $merged = [];
        $sections = [];

        foreach ($items as $item) {
            if (null === $item->id) {
                $merged[] = $item;

                continue;
            }

            if (isset($sections[$item->id])) {
                $sections[$item->id]->children = [...$sections[$item->id]->children, ...$item->children];

                continue;
            }

            $sections[$item->id] = $item;
            $merged[] = $item;
        }

        return $merged;
    }
}
