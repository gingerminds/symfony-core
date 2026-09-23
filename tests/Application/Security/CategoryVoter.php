<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\Security;

use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;
use Gingerminds\CoreBundle\Tests\Application\Entity\Category;

final class CategoryVoter extends AbstractResourceVoter
{
    protected function getResourceName(): string
    {
        return 'category';
    }

    protected function getSubjectClass(): string
    {
        return Category::class;
    }

    protected function getPermissionName(): string
    {
        return 'categories';
    }
}
