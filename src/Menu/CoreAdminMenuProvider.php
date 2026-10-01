<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Menu;

use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;

/**
 * Core entries. Their weights leave room on both sides: Dashboard -100, Administration 100,
 * whose children (users 0, contributors 10, roles 20, permissions 30) a bundle extends by
 * declaring the `administration` section too.
 */
final readonly class CoreAdminMenuProvider implements AdminMenuProviderInterface
{
    public const string DASHBOARD = 'dashboard';
    public const string ADMINISTRATION = 'administration';

    public function __construct(
        private ResourceRegistry $resources,
    ) {
    }

    public function getItems(): iterable
    {
        yield new MenuItem('menu.dashboard', 'gingerminds_core_dashboard', icon: 'bi-speedometer2', id: self::DASHBOARD, weight: -100);

        $children = [];
        $entries = [
            'user' => ['bi-person-lock', 0],
            'contributor' => ['bi-people', 10],
            'role' => ['bi-shield-lock', 20],
            'permission' => ['bi-key', 30],
        ];

        foreach ($entries as $name => [$icon, $weight]) {
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
                weight: $weight,
            );
        }

        yield new MenuItem('menu.administration', icon: 'bi-gear', children: $children, id: self::ADMINISTRATION, weight: 100);
    }
}
