<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository;

use ApiPlatform\Metadata\Parameters;
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
     * A page of the list query with the list parameters (admin lists): their API Platform filters
     * applied by the API Platform ParameterExtension, as on the API collections. The page size
     * defaults to the repository's, bounded by its maximum.
     *
     * @return Paginator<T>
     */
    public function paginate(Parameters $parameters, int $page = 1, ?int $itemsPerPage = null): Paginator;

    /**
     * The query every read starts from (admin lists and edit pages, API collections and items
     * through the Doctrine state options `repositoryMethod`): the context scope and the
     * `#[EagerLoad]` associations, root alias `o`.
     */
    public function createListQueryBuilder(): QueryBuilder;

    /**
     * The list query without its eager loads, context scope only (counts, facets, autocomplete).
     */
    public function createScopedQueryBuilder(): QueryBuilder;

    /**
     * Applies the valued list parameters (FilterParameters) to a query of the entity.
     */
    public function applyParameters(QueryBuilder $qb, Parameters $parameters): void;

    /**
     * @return T|null
     */
    public function findOneForRead(int|string $id): ?object;

    /**
     * @param T $entity
     */
    public function save(object $entity, bool $flush = true): void;

    /**
     * Saves the entity with $write, which persists and flushes it, run between the save hooks
     * (and in the transaction the repository needs). save() writes through it, so does the
     * API Platform Doctrine persist processor (RepositoryProcessor): same hooks for both.
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
