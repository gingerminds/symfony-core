# Cache

## What is cached

The **serialized API responses** of `Get`/`GetCollection` operations of resources
implementing `CacheableResourceInterface`:

```php
class Event implements ResourceInterface, CacheableResourceInterface
{
    use CacheableResourceTrait; // getCacheTtl(): null => gingerminds_core.cache.default_ttl

    public static function getCacheKey(): string
    {
        return 'event';
    }
}
```

> Change from the Laravel core, which cached query results (Eloquent models). Caching
> Doctrine entities is unsafe (detached entities, uninitialized proxies), so the bundle caches
> the final response instead: a hit never touches the database nor the serializer.

- Entries are tagged `{key}` + `{key}.list` (collections) or `{key}.item.{id}` (items).
- The key covers the path, the sorted query string, `Accept`, `Accept-Language` and the
  context from `CacheContextResolverInterface`.
- Responses carry `X-Gingerminds-Cache: HIT|MISS`.
- The pool is the tag-aware `gingerminds_core.resource_cache` (on `cache.app`); point
  `gingerminds_core.cache.pool` at a Redis pool in production.

## Security

The listener runs after the firewall (authentication applies) and before API Platform's read.
A collection operation's `security` expression is evaluated before serving a hit; item
operations whose `security` uses `object` are never cached. If a response depends on the
current user in any other way, add the user (or their audience) to the cache context — or
do not make the resource cacheable.

## Invalidation

On every Doctrine flush (`CacheInvalidationListener`):

- an inserted/updated/removed `CacheableResourceInterface` entity invalidates its
  `{key}.list` and `{key}.item.{id}` tags (collection changes included);
- a `CacheCascadeInterface` entity invalidates the whole `{key}` tag of every key returned by
  `getCascadeCacheKeys()` (translation rows, child rows embedded in a parent's response).

Tags are collected during the flush and invalidated once it succeeded. Writes done with DQL
`UPDATE`/`DELETE` bypass the unit of work: invalidate manually
(`$pool->invalidateTags([...])`, tag names from `CacheKeyBuilder`).

## Context axes

`CacheContextResolverInterface` returns the viewer axes changing a response (site, language,
country...). The bundle binds a no-op resolver to `gingerminds_core.cache.context_resolver`;
a multisite bundle or the project replaces the alias or decorates it:

```yaml
services:
    App\Cache\CountryContextResolver:
        decorates: gingerminds_core.cache.context_resolver
```

Return canonical ids: `fr` and `fr-FR` must produce the same key.

## Switches

- `gingerminds_core.cache.enabled: false` disables reads and invalidation (e.g. when Redis is down).
- `getCacheTtl()` returns seconds, `0` for no expiration, `null` for the default TTL.
