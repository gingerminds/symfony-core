<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Twig;

use Gingerminds\CoreBundle\ApiPlatform\Filter\FilterParameters;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Twig functions of the lists (`gm_list_path()`, `gm_sort_path()`, `gm_sort_direction()`), on the
 * list parameters of the current request (`order[...]`, `search`, filters, `page`,
 * `itemsPerPage`): a Twig runtime, instantiated on the first call only.
 */
final readonly class ListExtension
{
    private const array DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * The current list URL with the current query (empty values dropped) and $overrides (a null
     * override removes the parameter).
     *
     * @param array<string, mixed> $overrides
     * @param array<string, mixed> $routeParameters
     */
    #[AsTwigFunction('gm_list_path')]
    public function listPath(string $route, array $overrides = [], array $routeParameters = []): string
    {
        $parameters = self::withoutEmpty([...$this->query(), ...$overrides]);

        return $this->urlGenerator->generate($route, [...$routeParameters, ...$parameters]);
    }

    /**
     * The list sorted by $property only (`order[property]`), toggling its current direction, first page.
     *
     * @param array<string, mixed> $routeParameters
     */
    #[AsTwigFunction('gm_sort_path')]
    public function sortPath(string $route, string $property, array $routeParameters = []): string
    {
        $direction = 'asc' === $this->sortDirection($property) ? 'desc' : 'asc';

        return $this->listPath($route, [FilterParameters::ORDER => [$property => $direction], 'page' => null], $routeParameters);
    }

    /**
     * `asc`, `desc`, or null when the current request does not sort by $property.
     */
    #[AsTwigFunction('gm_sort_direction')]
    public function sortDirection(string $property): ?string
    {
        $order = $this->query()[FilterParameters::ORDER] ?? null;
        $direction = \is_array($order) && \is_string($order[$property] ?? null) ? strtolower($order[$property]) : null;

        return \in_array($direction, self::DIRECTIONS, true) ? $direction : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function query(): array
    {
        return $this->requestStack->getCurrentRequest()?->query->all() ?? [];
    }

    /**
     * @param array<array-key, mixed> $parameters
     *
     * @return array<array-key, mixed>
     */
    private static function withoutEmpty(array $parameters): array
    {
        $kept = [];

        foreach ($parameters as $key => $value) {
            if (\is_array($value)) {
                $value = self::withoutEmpty($value);
            }

            if (null !== $value && '' !== $value && [] !== $value) {
                $kept[$key] = $value;
            }
        }

        return $kept;
    }
}
