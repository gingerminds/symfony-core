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
     * @param list<MenuItem> $items
     *
     * @return list<MenuItem>
     */
    private function filter(array $items): array
    {
        usort($items, static fn (MenuItem $a, MenuItem $b): int => $b->priority <=> $a->priority);
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
}
