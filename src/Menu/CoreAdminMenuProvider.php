<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Menu;

use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;

final readonly class CoreAdminMenuProvider implements AdminMenuProviderInterface
{
    public function __construct(
        private ResourceRegistry $resources,
    ) {
    }

    public function getItems(): iterable
    {
        yield new MenuItem('menu.dashboard', 'gingerminds_core_dashboard', icon: 'bi-speedometer2', priority: 1000);

        $children = [];

        foreach (['user' => 'bi-person-lock', 'contributor' => 'bi-people', 'role' => 'bi-shield-lock', 'permission' => 'bi-key'] as $name => $icon) {
            if (!$this->resources->has($name)) {
                continue;
            }

            $resource = $this->resources->get($name);
            $children[] = new MenuItem(
                $resource->translationKey('name_p'),
                $resource->route('index'),
                icon: $icon,
                permission: AbstractResourceVoter::VIEW,
                permissionSubject: $name,
                translationDomain: $resource->translationDomain,
            );
        }

        yield new MenuItem('menu.administration', icon: 'bi-gear', children: $children, priority: -1000);
    }
}
