<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Voter\User;

use Gingerminds\CoreBundle\Entity\User\ContributorInterface;
use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;

class ContributorVoter extends AbstractResourceVoter
{
    protected function getResourceName(): string
    {
        return 'contributor';
    }

    protected function getSubjectClass(): string
    {
        return ContributorInterface::class;
    }

    protected function getPermissionName(): string
    {
        return 'contributors';
    }
}
