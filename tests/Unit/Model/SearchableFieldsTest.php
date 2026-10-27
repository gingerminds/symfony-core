<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\Model;

use Gingerminds\CoreBundle\Entity\Permission\Permission;
use Gingerminds\CoreBundle\Entity\User\Contributor;
use Gingerminds\CoreBundle\Model\SearchableFields;
use Gingerminds\CoreBundle\Tests\Application\Entity\Category;
use Gingerminds\CoreBundle\Tests\Application\Entity\Product;
use PHPUnit\Framework\TestCase;

final class SearchableFieldsTest extends TestCase
{
    public function testOwnAndAssociationPaths(): void
    {
        self::assertSame(['name', 'category.name'], SearchableFields::of(Product::class));
    }

    public function testPromotedAndParentPropertiesAreRead(): void
    {
        self::assertSame(['name'], SearchableFields::of(Category::class));
        self::assertSame(['lastname', 'firstname', 'trigram'], SearchableFields::of(Contributor::class));
        self::assertSame(['name'], SearchableFields::of(Permission::class));
    }

    public function testNoMarkedPropertyMeansNotSearchable(): void
    {
        self::assertSame([], SearchableFields::of(\ArrayObject::class));
    }
}
