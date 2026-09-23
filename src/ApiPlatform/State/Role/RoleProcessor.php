<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\State\Role;

use ApiPlatform\Metadata\Operation;
use Gingerminds\CoreBundle\ApiPlatform\State\ResourceProcessor;
use Gingerminds\CoreBundle\Entity\Role\RoleInterface;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @extends ResourceProcessor<RoleInterface>
 */
class RoleProcessor extends ResourceProcessor
{
    protected function remove(object $entity, Operation $operation): void
    {
        if (UserInterface::SUPER_ADMIN_ROLE === $entity->getName()) {
            throw new UnprocessableEntityHttpException($this->trans('role.error.super_admin_delete'));
        }

        parent::remove($entity, $operation);
    }
}
