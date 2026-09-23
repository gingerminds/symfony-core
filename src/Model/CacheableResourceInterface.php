<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

interface CacheableResourceInterface
{
    public static function getCacheKey(): string;

    /**
     * Seconds; null falls back to `gingerminds_core.cache.default_ttl`,
     * 0 means no expiration.
     */
    public static function getCacheTtl(): ?int;
}
