<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\Model;

use Gingerminds\CoreBundle\Entity\User\User;
use Gingerminds\CoreBundle\Model\SortableFields;
use Gingerminds\CoreBundle\Tests\Application\Entity\Product;
use PHPUnit\Framework\TestCase;

final class SortableFieldsTest extends TestCase
{
    public function testOwnAndAssociationPaths(): void
    {
        self::assertSame(['name', 'category.name'], SortableFields::of(Product::class));
    }

    public function testParentPropertiesAreRead(): void
    {
        self::assertSame(['email', 'contributor.lastname'], SortableFields::of(User::class));
        self::assertTrue(SortableFields::allows(User::class, 'email'));
        self::assertFalse(SortableFields::allows(User::class, 'password'));
        self::assertFalse(SortableFields::allows(User::class, 'contributor'));
    }
}
