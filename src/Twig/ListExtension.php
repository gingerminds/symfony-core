<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Twig;

use Gingerminds\CoreBundle\Repository\ListQuery;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Twig functions of the lists (`gm_list_path()`, `gm_sort_path()`, `gm_sort_direction()`):
 * a Twig runtime, instantiated on the first call only.
 */
final readonly class ListExtension
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param array<string, mixed> $overrides
     * @param array<string, mixed> $routeParameters
     */
    #[AsTwigFunction('gm_list_path')]
    public function listPath(string $route, ListQuery $query, array $overrides = [], array $routeParameters = []): string
    {
        $parameters = array_filter(
            [...$query->toQueryParameters(), ...$overrides],
            static fn (mixed $value): bool => null !== $value && '' !== $value,
        );

        return $this->urlGenerator->generate($route, [...$routeParameters, ...$parameters]);
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    #[AsTwigFunction('gm_sort_path')]
    public function sortPath(string $route, ListQuery $query, string $property, array $routeParameters = []): string
    {
        $sort = $query->sortBy === $property && ListQuery::SORT_ASC === $query->sort ? ListQuery::SORT_DESC : ListQuery::SORT_ASC;

        return $this->listPath($route, $query, ['sortBy' => $property, 'sort' => $sort, 'page' => null], $routeParameters);
    }

    #[AsTwigFunction('gm_sort_direction')]
    public static function sortDirection(ListQuery $query, string $property): ?string
    {
        return $query->sortBy === $property ? $query->sort : null;
    }
}
