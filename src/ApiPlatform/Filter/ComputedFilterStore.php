<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Filter;

use Symfony\Contracts\Service\ResetInterface;

/**
 * @phpstan-type ComputedFilters array<string, array{type: string, options: list<array<string, mixed>>|array<string, mixed>}>
 */
final class ComputedFilterStore implements ResetInterface
{
    /** @var array<class-string, ComputedFilters> */
    private array $filters = [];

    /**
     * @param class-string    $resourceClass
     * @param ComputedFilters $filters
     */
    public function set(string $resourceClass, array $filters): void
    {
        $this->filters[$resourceClass] = $filters;
    }

    /**
     * @return ComputedFilters
     */
    public function get(string $resourceClass): array
    {
        return $this->filters[$resourceClass] ?? [];
    }

    public function has(string $resourceClass): bool
    {
        return [] !== ($this->filters[$resourceClass] ?? []);
    }

    public function reset(): void
    {
        $this->filters = [];
    }
}
