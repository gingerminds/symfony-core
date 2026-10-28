<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\Model;

use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Entity\User\User;
use Gingerminds\CoreBundle\Model\EagerLoad;
use Gingerminds\CoreBundle\Model\EagerLoads;
use Gingerminds\CoreBundle\Tests\Application\Entity\Product;
use PHPUnit\Framework\TestCase;

final class EagerLoadsTest extends TestCase
{
    public function testMarkedAssociations(): void
    {
        self::assertSame(['category'], EagerLoads::of(Product::class));
        self::assertSame(['permissions'], EagerLoads::of(Role::class));
    }

    public function testParentAndTraitPropertiesAreRead(): void
    {
        self::assertSame(['contributor', 'roleEntities'], EagerLoads::of(User::class));
    }

    public function testPathsLoadAssociationsOfTheRelatedEntity(): void
    {
        self::assertSame(['category', 'translations.language', 'translations.site'], EagerLoads::of(EagerLoadFixture::class));
    }
}

final class EagerLoadFixture
{
    #[EagerLoad]
    public ?object $category = null;

    /** @var list<object> */
    #[EagerLoad(paths: ['language', 'site'])]
    public array $translations = [];
}
