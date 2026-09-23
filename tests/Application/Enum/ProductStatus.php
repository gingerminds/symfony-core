<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\Enum;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
