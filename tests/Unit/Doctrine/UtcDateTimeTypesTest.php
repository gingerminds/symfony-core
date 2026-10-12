<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\Doctrine;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Gingerminds\CoreBundle\Doctrine\Type\UtcDateTimeImmutableType;
use Gingerminds\CoreBundle\Doctrine\Type\UtcDateTimeType;
use PHPUnit\Framework\TestCase;

final class UtcDateTimeTypesTest extends TestCase
{
    public function testImmutableIsWrittenAndReadInUtc(): void
    {
        $type = new UtcDateTimeImmutableType();
        $platform = new MySQLPlatform();
        $paris = new \DateTimeImmutable('2026-07-01 10:30:00', new \DateTimeZone('Europe/Paris'));

        self::assertSame('2026-07-01 08:30:00', $type->convertToDatabaseValue($paris, $platform));
        self::assertNull($type->convertToDatabaseValue(null, $platform));

        $read = $type->convertToPHPValue('2026-07-01 08:30:00', $platform);
        self::assertSame('UTC', $read?->getTimezone()->getName());
        self::assertEquals($paris, $read);
    }

    public function testMutableIsWrittenInUtcWithoutChangingTheInstance(): void
    {
        $type = new UtcDateTimeType();
        $platform = new MySQLPlatform();
        $paris = new \DateTime('2026-01-15 10:30:00', new \DateTimeZone('Europe/Paris'));

        self::assertSame('2026-01-15 09:30:00', $type->convertToDatabaseValue($paris, $platform));
        self::assertSame('Europe/Paris', $paris->getTimezone()->getName());

        $read = $type->convertToPHPValue('2026-01-15 09:30:00', $platform);
        self::assertSame('UTC', $read?->getTimezone()->getName());
        self::assertEquals($paris, $read);
    }
}
