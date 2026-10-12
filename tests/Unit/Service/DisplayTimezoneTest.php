<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\Service;

use Gingerminds\CoreBundle\Service\DisplayTimezone;
use PHPUnit\Framework\TestCase;

final class DisplayTimezoneTest extends TestCase
{
    public function testDefaultsToEuropeParis(): void
    {
        self::assertSame('Europe/Paris', new DisplayTimezone()->getName());
    }

    public function testToUtc(): void
    {
        $timezone = new DisplayTimezone();

        self::assertSame('2026-07-01 08:30:00 UTC', $timezone->toUtc('2026-07-01 10:30')?->format('Y-m-d H:i:s T'));
        self::assertSame('2026-01-15 09:30:00 UTC', $timezone->toUtc('2026-01-15T10:30')?->format('Y-m-d H:i:s T'));
        self::assertNull($timezone->toUtc(null));
        self::assertNull($timezone->toUtc(''));
        self::assertNull($timezone->toUtc('not a date'));
    }

    public function testToDisplay(): void
    {
        $timezone = new DisplayTimezone();

        // A string without offset is a stored (UTC) value.
        self::assertSame('2026-07-01 10:30', $timezone->toDisplay('2026-07-01 08:30:00')?->format('Y-m-d H:i'));
        self::assertSame(
            '2026-07-01 10:30',
            $timezone->toDisplay(new \DateTimeImmutable('2026-07-01 08:30', new \DateTimeZone('UTC')))?->format('Y-m-d H:i'),
        );
        self::assertSame('Europe/Paris', $timezone->toDisplay(new \DateTime('now'))?->getTimezone()->getName());
        self::assertNull($timezone->toDisplay(null));
    }

    public function testDayRangeToUtc(): void
    {
        [$from, $to] = new DisplayTimezone()->dayRangeToUtc('2026-07-01', '2026-07-31');

        self::assertSame('2026-06-30 22:00:00 UTC', $from?->format('Y-m-d H:i:s T'));
        self::assertSame('2026-07-31 21:59:59 UTC', $to?->format('Y-m-d H:i:s T'));
    }

    public function testDayRangeIgnoresInvalidBounds(): void
    {
        $timezone = new DisplayTimezone('UTC');

        self::assertSame([null, null], $timezone->dayRangeToUtc(null, ''));
        self::assertSame([null, null], $timezone->dayRange('01/07/2026', ['2026-07-01']));
    }

    public function testDayRangeStaysInDisplayTimezone(): void
    {
        [$from, $to] = new DisplayTimezone('America/New_York')->dayRange('2026-07-01', '2026-07-01');

        self::assertSame('2026-07-01 00:00:00 America/New_York', $from?->format('Y-m-d H:i:s e'));
        self::assertSame('2026-07-01 23:59:59 America/New_York', $to?->format('Y-m-d H:i:s e'));
    }
}
