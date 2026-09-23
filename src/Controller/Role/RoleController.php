<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\Role;

use Gingerminds\CoreBundle\Controller\AbstractCrudController;
use Gingerminds\CoreBundle\Entity\Permission\PermissionInterface;
use Gingerminds\CoreBundle\Entity\Role\RoleInterface;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Repository\Permission\PermissionRepository;
use Symfony\Component\Form\FormInterface;

class RoleController extends AbstractCrudController
{
    protected function getResourceName(): string
    {
        return 'role';
    }

    protected function getDeleteError(object $entity): ?string
    {
        if ($entity instanceof RoleInterface && UserInterface::SUPER_ADMIN_ROLE === $entity->getName()) {
            return $this->trans('role.error.super_admin_delete');
        }

        return null;
    }

    protected function getFormParameters(object $entity, FormInterface $form, bool $isNew): array
    {
        $repository = $this->context->doctrine->getRepository($this->context->resources->getEntityClass('permission'));
        $groups = [];

        if ($repository instanceof PermissionRepository) {
            foreach ($repository->findAllGrouped() as $group => $permissions) {
                $groups[$group] = array_map(static fn (PermissionInterface $permission): string => (string) $permission->getId(), $permissions);
            }
        }

        return ['permission_groups' => $groups];
    }
}
