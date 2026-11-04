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

    /**
     * The key of an API response: the context, the path, the query parameters (only the
     * $queryParameters ones when given, see queryParameterTree()) and the negotiation headers.
     *
     * @param array<string, mixed>|null $queryParameters
     */
    public function responseKey(string $resourceTag, Request $request, ?array $queryParameters = null): string
    {
        $query = $request->query->all();

        if (null !== $queryParameters) {
            $query = self::keepParameters($query, $queryParameters);
        }

        self::ksortRecursive($query);

        return $this->sanitize($resourceTag) . '.' . hash('xxh128', serialize([
            $this->context(),
            $request->getPathInfo(),
            $query,
            $request->headers->get('Accept'),
            $request->headers->get('Accept-Language'),
        ]));
    }

    /**
     * The tree of query parameter keys (`order[code]`, `publishedAt`) a response may depend on:
     * a leaf (true) keeps the whole value of its key, `publishedAt[after]` as `category[]`.
     *
     * @param iterable<string> $keys
     *
     * @return array<string, mixed>
     */
    public static function queryParameterTree(iterable $keys): array
    {
        $tree = [];

        foreach ($keys as $key) {
            // `order[code][]` / a `:property` placeholder: the parent key keeps its whole value.
            $key = (string) preg_replace('/(\[\]|\[[^\]]*:property[^\]]*\]).*$/', '', $key);
            $tree = self::addPath($tree, explode('[', str_replace(']', '', $key)));
        }

        return $tree;
    }

    /**
     * @param array<string, mixed> $tree
     * @param list<string>         $segments
     *
     * @return array<string, mixed>
     */
    private static function addPath(array $tree, array $segments): array
    {
        $segment = (string) array_shift($segments);
        $node = $tree[$segment] ?? [];

        // A leaf already keeps the whole value of its key.
        if (true !== $node) {
            $tree[$segment] = [] === $segments ? true : self::addPath(\is_array($node) ? $node : [], $segments);
        }

        return $tree;
    }

    /**
     * @param array<mixed>         $query
     * @param array<string, mixed> $tree
     *
     * @return array<mixed>
     */
    private static function keepParameters(array $query, array $tree): array
    {
        $kept = [];

        foreach ($query as $key => $value) {
            $node = $tree[$key] ?? null;

            if (true === $node) {
                $kept[$key] = $value;
            } elseif (\is_array($node) && \is_array($value)) {
                $kept[$key] = self::keepParameters($value, $node);
            }
        }

        return $kept;
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
