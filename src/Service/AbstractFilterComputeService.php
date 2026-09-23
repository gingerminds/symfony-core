<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Model\FilterableInterface;
use Gingerminds\CoreBundle\Repository\ListQuery;
use Symfony\Contracts\Service\Attribute\Required;

use function in_array;
use function is_array;

/**
 * @template T of FilterableInterface
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
     * @return class-string<T>
     */
    abstract protected function getEntityClass(): string;

    /**
     * @return array<string, array{min: ?string, max: ?string, years: list<int>}>
     */
    abstract protected function dateFacetStats(ListQuery $query): array;

    /**
     * @return array<string, array{type: string, options: array{min: ?string, max: ?string, years: list<int>}}>
     */
    public function computeDateFilters(ListQuery $query): array
    {
        $configs = $this->getEntityClass()::getFilters();
        $filters = [];

        foreach ($this->dateFacetStats($query) as $key => $stats) {
            if (null === $stats['min']) {
                continue;
            }

            $filters[$key] = [
                'type' => $configs[$key]['type'] ?? 'date',
                'options' => $stats,
            ];
        }

        return $filters;
    }

    protected function activeFilterValue(ListQuery $query, string $key): mixed
    {
        return $query->getFilter($key);
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
        if (null === $raw || '' === $raw || 'all' === $raw) {
            return [];
        }

        $values = is_array($raw) ? $raw : [$raw];

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
            $qb->orderBy('c.sortOrder', 'ASC');
        }

        $options = [];

        foreach ($qb->getQuery()->getResult() as $category) {
            if (!\is_object($category) || !method_exists($category, 'getId')) {
                continue;
            }

            $id = (int) $category->getId();
            $total = $counts[$id] ?? 0;

            if (0 === $total && !in_array($id, $selectedIds, true)) {
                continue;
            }

            $label = method_exists($category, 'getName') ? $category->getName() : null;
            $label ??= method_exists($category, 'getCode') ? $category->getCode() : (string) $id;

            $options[] = ['value' => $id, 'label' => (string) $label, 'total' => $total];
        }

        return $options;
    }
}
