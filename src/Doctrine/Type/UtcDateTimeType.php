<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;

/**
 * Overrides `datetime`: written in UTC whatever the timezone of the instance, read as UTC.
 */
final class UtcDateTimeType extends DateTimeType
{
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof \DateTime) {
            // Never mutate the entity's own instance.
            $value = (clone $value)->setTimezone(new \DateTimeZone('UTC'));
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTime
    {
        if (null === $value || $value instanceof \DateTime) {
            return $value;
        }

        $utc = new \DateTimeZone('UTC');
        $dateTime = \DateTime::createFromFormat($platform->getDateTimeFormatString(), (string) $value, $utc);

        if (false !== $dateTime) {
            return $dateTime;
        }

        try {
            return new \DateTime((string) $value, $utc);
        } catch (\Exception $e) {
            throw InvalidFormat::new((string) $value, self::class, $platform->getDateTimeFormatString(), $e);
        }
    }
}
