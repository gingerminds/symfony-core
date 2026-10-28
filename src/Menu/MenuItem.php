<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Menu;

final class MenuItem
{
    /**
     * @param array<string, mixed> $routeParameters
     * @param list<MenuItem>       $children
     * @param string|null          $id              entry identifier, found by the AdminMenuBuiltEvent listeners; the
     *                                              entries of one level sharing it are merged into one (children
     *                                              added, label/icon of the first one)
     * @param int                  $weight          ascending order: the lowest weight first, the registration
     *                                              order on a tie
     */
    public function __construct(
        public readonly string $label,
        public readonly ?string $route = null,
        public readonly array $routeParameters = [],
        public readonly ?string $icon = null,
        public readonly ?string $permission = null,
        public readonly mixed $permissionSubject = null,
        public array $children = [],
        public readonly string $translationDomain = 'GingermindsCore',
        public readonly ?string $id = null,
        public readonly int $weight = 0,
    ) {
    }

    /**
     * A copy with the given constructor arguments changed, e.g. `$item->with(icon: 'bi-person', weight: 5)`.
     */
    public function with(mixed ...$changes): self
    {
        return new self(...[
            'label' => $this->label,
            'route' => $this->route,
            'routeParameters' => $this->routeParameters,
            'icon' => $this->icon,
            'permission' => $this->permission,
            'permissionSubject' => $this->permissionSubject,
            'children' => $this->children,
            'translationDomain' => $this->translationDomain,
            'id' => $this->id,
            'weight' => $this->weight,
            ...$changes,
        ]);
    }

    public function getActivePrefix(): ?string
    {
        return null !== $this->route ? preg_replace('/_(index|new|edit|delete)$/', '', $this->route) : null;
    }
}
