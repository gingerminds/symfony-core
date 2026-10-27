<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository;

use Doctrine\ORM\QueryBuilder;
use Gingerminds\CoreBundle\Pagination\Paginator;

/**
 * @template T of object
 */
interface RepositoryInterface
{
    /**
     * @return class-string<T>
     */
    public function getEntityClass(): string;

    /**
     * @return Paginator<T>
     */
    public function paginate(ListQuery $query): Paginator;

    /**
     * @return list<T>
     */
    public function findForList(ListQuery $query): array;

    /**
     * @param list<string> $excludedFilters filter keys not applied (facets)
     */
    public function createListQueryBuilder(ListQuery $query, array $excludedFilters = []): QueryBuilder;

    /**
     * @return T|null
     */
    public function findOneForRead(int|string $id, ?ListQuery $query = null): ?object;

    /**
     * @param T $entity
     */
    public function save(object $entity, bool $flush = true): void;

    /**
     * Saves the entity with $write, which persists and flushes it, run between the save hooks
     * (and in the transaction the repository needs). save() writes through it, so can another
     * writer (e.g. an API Platform processor): same hooks for both.
     *
     * @template R
     *
     * @param T             $entity
     * @param \Closure(): R $write
     *
     * @return R
     */
    public function saveWith(object $entity, \Closure $write): mixed;

    /**
     * @param T $entity
     */
    public function remove(object $entity, bool $flush = true): void;

    /**
     * Removes the entity with $write, which removes and flushes it, run after the remove hook.
     *
     * @template R
     *
     * @param T             $entity
     * @param \Closure(): R $write
     *
     * @return R
     */
    public function removeWith(object $entity, \Closure $write): mixed;
}
