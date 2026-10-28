<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Dates do not depend on the PHP default timezone (Europe/Paris in phpunit.xml.dist).
 */
final class DatesTest extends KernelTestCase
{
    public function testTheBundleKeepsThePhpDefaultTimezone(): void
    {
        self::bootKernel();

        self::assertSame('Europe/Paris', date_default_timezone_get());
    }

    public function testTheSerializerWritesDatesInUtc(): void
    {
        $date = new \DateTimeImmutable('2026-07-01 10:30', new \DateTimeZone('Europe/Paris'));

        self::assertSame('2026-07-01T08:30:00+00:00', self::getContainer()->get(NormalizerInterface::class)->normalize($date));
    }
}
