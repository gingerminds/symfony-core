<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Service;

use ApiPlatform\Metadata\Parameters;
use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\ApiPlatform\Filter\FilterParameters;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Computed filters (facets) of a collection, from its valued list parameters (the collection
 * operation's on the API): stored in ComputedFilterStore by a provider, added to the Hydra
 * collection as `filters` by ComputedFiltersCollectionNormalizer.
 */
abstract class AbstractFilterComputeService
{
    protected ?EntityManagerInterface $entityManager = null;

    #[Required]
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @return array<string, array{min: ?string, max: ?string, years: list<int>}>
     */
    abstract protected function dateFacetStats(Parameters $parameters): array;

    /**
     * @return array<string, array{type: string, options: array{min: ?string, max: ?string, years: list<int>}}>
     */
    public function computeDateFilters(Parameters $parameters): array
    {
        $filters = [];

        foreach ($this->dateFacetStats($parameters) as $key => $stats) {
            if (null !== $stats['min']) {
                $filters[$key] = ['type' => 'date', 'options' => $stats];
            }
        }

        return $filters;
    }

    protected function activeFilterValue(Parameters $parameters, string $key): mixed
    {
        return FilterParameters::value($parameters, $key);
    }

    /**
     * @param list<int> $ids
     * @param list<int> $selectedIds
     *
     * @return list<int>
     */
    protected function mergeWithSelected(array $ids, array $selectedIds): array
    {
        return array_values(array_unique([...$ids, ...$selectedIds]));
    }

    /**
     * @return list<int>
     */
    protected function normalizeIds(mixed $raw): array
    {
        if (null === $raw || '' === $raw) {
            return [];
        }

        $values = \is_array($raw) ? $raw : [$raw];

        return array_values(array_map(intval(...), array_filter($values, is_numeric(...))));
    }

    /**
     * Category-shaped facet: counted ids merged with the selected ones,
     * empty unselected options dropped, labelled by `name` (fallback `code`).
     *
     * @param class-string           $categoryClass
     * @param array<int|string, int> $counts        category id => total
     * @param list<int>              $selectedIds
     *
     * @return list<array{value: int, label: string, total: int}>
     */
    protected function resolveCategoryOptions(string $categoryClass, array $counts, array $selectedIds, bool $orderBySortOrder = false): array
    {
        $ids = $this->mergeWithSelected(array_map(intval(...), array_keys($counts)), $selectedIds);

        if ([] === $ids || !$this->entityManager instanceof EntityManagerInterface) {
            return [];
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from($categoryClass, 'c')
            ->andWhere('c.id IN (:ids)')
            ->setParameter('ids', $ids);

        if ($orderBySortOrder) {
            $qb->orderBy('c.sortOrder', \SortDirection::Ascending);
        }

        $options = [];

        foreach ($qb->getQuery()->getResult() as $category) {
            if (!\is_object($category) || !method_exists($category, 'getId')) {
                continue;
            }

            $id = (int) $category->getId();
            $total = $counts[$id] ?? 0;

            if (0 === $total && !\in_array($id, $selectedIds, true)) {
                continue;
            }

            $label = method_exists($category, 'getName') ? $category->getName() : null;
            $label ??= method_exists($category, 'getCode') ? $category->getCode() : (string) $id;

            $options[] = ['value' => $id, 'label' => (string) $label, 'total' => $total];
        }

        return $options;
    }
}
