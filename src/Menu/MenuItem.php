<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Menu;

final class MenuItem
{
    /**
     * @param array<string, mixed> $routeParameters
     * @param list<MenuItem>       $children
     */
    public function __construct(
        public readonly string $label,
        public readonly ?string $route = null,
        public readonly array $routeParameters = [],
        public readonly ?string $icon = null,
        public readonly ?string $permission = null,
        public readonly mixed $permissionSubject = null,
        public array $children = [],
        public readonly int $priority = 0,
        public readonly string $translationDomain = 'GingermindsCore',
    ) {
    }

    public function getActivePrefix(): ?string
    {
        return null !== $this->route ? preg_replace('/_(index|new|edit|delete)$/', '', $this->route) : null;
    }
}
