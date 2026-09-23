<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Cache;

use Symfony\Component\HttpFoundation\Request;

final readonly class CacheKeyBuilder
{
    public function __construct(
        private CacheContextResolverInterface $contextResolver,
    ) {
    }

    /**
     * @return array<string, int|string|null>
     */
    public function context(): array
    {
        return $this->contextResolver->resolve();
    }

    public function itemTag(string $resourceTag, int|string $id): string
    {
        return $this->sanitize($resourceTag . '.item.' . $id);
    }

    public function listTag(string $resourceTag): string
    {
        return $this->sanitize($resourceTag . '.list');
    }

    public function resourceTag(string $resourceTag): string
    {
        return $this->sanitize($resourceTag);
    }

    public function responseKey(string $resourceTag, Request $request): string
    {
        $query = $request->query->all();
        self::ksortRecursive($query);

        return $this->sanitize($resourceTag) . '.' . hash('xxh128', serialize([
            $this->context(),
            $request->getPathInfo(),
            $query,
            $request->headers->get('Accept'),
            $request->headers->get('Accept-Language'),
        ]));
    }

    private function sanitize(string $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_.\-]/', '_', $value);
    }

    /**
     * @param array<mixed> $array
     */
    private static function ksortRecursive(array &$array): void
    {
        ksort($array);

        foreach ($array as &$value) {
            if (\is_array($value)) {
                self::ksortRecursive($value);
            }
        }
    }
}
