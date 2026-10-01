<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Query;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Model\EagerLoadableInterface;

/**
 * Eager loads of an EagerLoadableInterface entity: to-one paths are fetch-joined in the
 * list query, paths going through a collection are loaded after the page.
 */
final readonly class EagerLoader
{
    /**
     * @param class-string $entityClass
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $entityClass,
    ) {
    }

    /**
     * Fetch-joins the eager loads; with $collections false, only the to-one paths (a joined
     * collection turns the paginated query into DISTINCT id subqueries over the whole table).
     */
    public function apply(QueryBuilderHelper $helper, bool $collections = true): void
    {
        foreach ($this->paths() as $path) {
            if ($collections || !$this->isCollectionPath($path)) {
                $helper->eagerLoad($path);
            }
        }
    }

    /**
     * Loads the eager loads going through a collection for already fetched entities, one
     * query per path (WHERE IN): Doctrine fills their uninitialized collections.
     *
     * @param list<object> $items
     */
    public function loadCollections(array $items, string $alias): void
    {
        if ([] === $items) {
            return;
        }

        foreach ($this->paths() as $path) {
            if (!$this->isCollectionPath($path)) {
                continue;
            }

            $qb = $this->entityManager->createQueryBuilder()->select($alias)->from($this->entityClass, $alias);
            new QueryBuilderHelper($qb, $alias, $this->entityManager)->eagerLoad($path);
            $qb->where($alias . ' IN (:gm_items)')
                ->setParameter('gm_items', $items)
                ->getQuery()
                ->getResult();
        }
    }

    /**
     * @return list<string>
     */
    private function paths(): array
    {
        return is_subclass_of($this->entityClass, EagerLoadableInterface::class) ? $this->entityClass::getEagerLoads() : [];
    }

    private function isCollectionPath(string $path): bool
    {
        $metadata = $this->entityManager->getClassMetadata($this->entityClass);

        foreach (explode('.', $path) as $association) {
            if (!$metadata->hasAssociation($association)) {
                return false;
            }

            if ($metadata->isCollectionValuedAssociation($association)) {
                return true;
            }

            $metadata = $this->entityManager->getClassMetadata($metadata->getAssociationTargetClass($association));
        }

        return false;
    }
}
