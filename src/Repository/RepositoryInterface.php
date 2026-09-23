<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository;

use Doctrine\ORM\QueryBuilder;
use Gingerminds\CoreBundle\Pagination\Paginator;
use Symfony\Component\Form\FormInterface;

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
     * @param T                         $entity
     * @param FormInterface<mixed>|null $form   the submitted form the entity comes from, if any
     */
    public function save(object $entity, ?FormInterface $form = null, bool $flush = true): void;

    /**
     * @param T $entity
     */
    public function remove(object $entity, bool $flush = true): void;
}
