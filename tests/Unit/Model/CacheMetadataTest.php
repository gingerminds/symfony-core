<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\Model;

use Gingerminds\CoreBundle\Model\Cacheable;
use Gingerminds\CoreBundle\Model\CacheCascade;
use Gingerminds\CoreBundle\Model\CacheMetadata;
use Gingerminds\CoreBundle\Tests\Application\Entity\Product;
use PHPUnit\Framework\TestCase;

final class CacheMetadataTest extends TestCase
{
    public function testCacheableResource(): void
    {
        $cacheable = CacheMetadata::cacheable(Product::class);

        self::assertInstanceOf(Cacheable::class, $cacheable);
        self::assertSame('product', $cacheable->key);
        self::assertNull($cacheable->ttl);
        self::assertSame([], CacheMetadata::cascadeKeys(Product::class));
        self::assertNull(CacheMetadata::cacheable(\stdClass::class));
    }

    public function testParentAttributesAreInherited(): void
    {
        self::assertSame('base', CacheMetadata::cacheable(InheritingCacheFixture::class)?->key);
        self::assertSame(['parent'], CacheMetadata::cascadeKeys(InheritingCacheFixture::class));
    }

    public function testTheClosestDeclarationWins(): void
    {
        $cacheable = CacheMetadata::cacheable(OverridingCacheFixture::class);

        self::assertSame('override', $cacheable?->key);
        self::assertSame(0, $cacheable->ttl);
        self::assertSame(['parent'], CacheMetadata::cascadeKeys(OverridingCacheFixture::class));
    }
}

#[Cacheable(key: 'base', ttl: 60)]
#[CacheCascade(['parent'])]
abstract class BaseCacheFixture
{
}

final class InheritingCacheFixture extends BaseCacheFixture
{
}

#[Cacheable(key: 'override', ttl: 0)]
final class OverridingCacheFixture extends BaseCacheFixture
{
}
