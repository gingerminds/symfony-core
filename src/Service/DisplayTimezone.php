<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Service;

/**
 * Dates are stored in UTC; back-office inputs, displays and date filters use `gingerminds_core.display_timezone`.
 */
final readonly class DisplayTimezone
{
    public const string DEFAULT = 'Europe/Paris';

    private \DateTimeZone $timezone;

    private \DateTimeZone $utc;

    public function __construct(string $timezone = self::DEFAULT)
    {
        $this->timezone = new \DateTimeZone($timezone);
        $this->utc = new \DateTimeZone('UTC');
    }

    public function getName(): string
    {
        return $this->timezone->getName();
    }

    public function getTimezone(): \DateTimeZone
    {
        return $this->timezone;
    }

    /**
     * A date typed in the display timezone (`Y-m-d H:i:s`, `Y-m-d\TH:i`...), converted to UTC.
     */
    public function toUtc(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, $this->timezone)->setTimezone($this->utc);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * A date (a string without offset is read as UTC, as stored) converted to the display timezone.
     */
    public function toDisplay(\DateTimeInterface|string|null $date): ?\DateTimeImmutable
    {
        if (null === $date || '' === $date) {
            return null;
        }

        try {
            $date = $date instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromInterface($date)
                : new \DateTimeImmutable($date, $this->utc);
        } catch (\Exception) {
            return null;
        }

        return $date->setTimezone($this->timezone);
    }

    /**
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable} start and end of the `Y-m-d` days, in the display timezone
     */
    public function dayRange(mixed $from, mixed $to): array
    {
        return [
            $this->day($from)?->setTime(0, 0),
            $this->day($to)?->setTime(23, 59, 59),
        ];
    }

    /**
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable} UTC bounds of the `Y-m-d` days, read in the display timezone
     */
    public function dayRangeToUtc(mixed $from, mixed $to): array
    {
        return array_map(
            fn (?\DateTimeImmutable $bound): ?\DateTimeImmutable => $bound?->setTimezone($this->utc),
            $this->dayRange($from, $to),
        );
    }

    private function day(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $this->timezone);

        return false === $date ? null : $date;
    }
}
