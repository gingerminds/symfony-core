<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\HeaderParameter;
use Gingerminds\CoreBundle\ApiPlatform\Metadata\HeaderParameterRegistry;
use Gingerminds\CoreBundle\Model\SearchableInterface;
use Gingerminds\CoreBundle\Model\Trait\TimestampableTrait;
use PHPUnit\Framework\TestCase;

final class HeaderParameterRegistryTest extends TestCase
{
    public function testMatchesTraitsThroughTheHierarchyAndInterfaces(): void
    {
        $registry = new HeaderParameterRegistry();
        $registry->register(TimestampableTrait::class, new HeaderParameter(key: 'X-Site-Id'));
        $registry->register(SearchableInterface::class, new HeaderParameter(key: 'Accept-Language'));

        $keys = array_map(
            static fn (HeaderParameter $parameter): ?string => $parameter->getKey(),
            $registry->for(ChildFixture::class),
        );

        self::assertSame(['X-Site-Id'], $keys);
        self::assertSame([], $registry->for('Unknown\Class'));
    }
}

abstract class ParentFixture
{
    use TimestampableTrait;
}

final class ChildFixture extends ParentFixture
{
}
