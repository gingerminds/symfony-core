<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

/**
 * A resource whose API responses are cached (`Get` / `GetCollection`), tagged `key`, `key.list`
 * and `key.item.{id}`, invalidated when one of its entities is written. Read from the class or
 * its parents (CacheMetadata), the closest declaration winning.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Cacheable
{
    /**
     * @param int|null $ttl seconds; null falls back to `gingerminds_core.cache.default_ttl`, 0 means no expiration
     */
    public function __construct(
        public string $key,
        public ?int $ttl = null,
    ) {
    }
}
