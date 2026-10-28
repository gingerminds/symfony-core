<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\Role;

use Gingerminds\CoreBundle\Controller\AbstractCrudController;
use Gingerminds\CoreBundle\Entity\Permission\PermissionInterface;
use Gingerminds\CoreBundle\Repository\Permission\PermissionRepository;
use Symfony\Component\Form\FormInterface;

class RoleController extends AbstractCrudController
{
    protected function getFormParameters(object $entity, FormInterface $form, bool $isNew): array
    {
        $repository = $this->getDoctrine()->getRepository($this->getResourceRegistry()->getEntityClass('permission'));
        $groups = [];

        if ($repository instanceof PermissionRepository) {
            foreach ($repository->findAllGrouped() as $group => $permissions) {
                $groups[$group] = array_map(static fn (PermissionInterface $permission): string => (string) $permission->getId(), $permissions);
            }
        }

        return ['permission_groups' => $groups];
    }
}
