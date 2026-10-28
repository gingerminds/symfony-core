<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Menu;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
        private readonly EventDispatcherInterface $dispatcher,
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

        $event = $this->dispatcher->dispatch(new AdminMenuBuiltEvent($this->merge($items)));

        return $this->items = $this->filter($event->getItems());
    }

    /**
     * Sorts by weight (stable: registration order on a tie), drops the entries not granted and
     * the empty sections, at every level.
     *
     * @param list<MenuItem> $items
     *
     * @return list<MenuItem>
     */
    private function filter(array $items): array
    {
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
     * Merges the entries sharing an id, at every level.
     *
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

        foreach ($merged as $item) {
            $item->children = $this->merge($item->children);
        }

        return $merged;
    }
}
