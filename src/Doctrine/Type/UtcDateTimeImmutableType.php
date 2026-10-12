<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;

/**
 * Overrides `datetime_immutable`: written in UTC whatever the timezone of the instance, read as UTC.
 */
final class UtcDateTimeImmutableType extends DateTimeImmutableType
{
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof \DateTimeImmutable) {
            $value = $value->setTimezone(new \DateTimeZone('UTC'));
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if (null === $value || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        $utc = new \DateTimeZone('UTC');
        $dateTime = \DateTimeImmutable::createFromFormat($platform->getDateTimeFormatString(), (string) $value, $utc);

        if (false !== $dateTime) {
            return $dateTime;
        }

        try {
            return new \DateTimeImmutable((string) $value, $utc);
        } catch (\Exception $e) {
            throw InvalidFormat::new((string) $value, self::class, $platform->getDateTimeFormatString(), $e);
        }
    }
}
