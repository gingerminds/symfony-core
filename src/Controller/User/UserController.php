<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\User;

use Gingerminds\CoreBundle\Controller\AbstractCrudController;
use Gingerminds\CoreBundle\Entity\User\UserInterface;

class UserController extends AbstractCrudController
{
    protected function getResourceName(): string
    {
        return 'user';
    }

    protected function getFormOptions(object $entity, bool $isNew): array
    {
        return ['password_required' => $isNew];
    }

    protected function getLabel(object $entity): string
    {
        return $entity instanceof UserInterface ? (string) $entity->getEmail() : parent::getLabel($entity);
    }
}
