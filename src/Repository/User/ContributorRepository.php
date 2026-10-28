<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\User;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Entity\User\ContributorInterface;
use Gingerminds\CoreBundle\Repository\AbstractRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @extends AbstractRepository<ContributorInterface>
 */
class ContributorRepository extends AbstractRepository implements ContributorRepositoryInterface
{
    protected int $itemsPerPage = 25;

    /**
     * @param class-string<ContributorInterface> $entityClass
     */
    public function __construct(
        ManagerRegistry $registry,
        #[Autowire(param: 'gingerminds_core.resource.contributor.entity')]
        string $entityClass,
    ) {
        parent::__construct($registry, $entityClass);
    }

    /**
     * @param ContributorInterface $entity
     */
    public function saveWith(object $entity, \Closure $write): mixed
    {
        return $this->writeUserLinks(fn (): mixed => parent::saveWith($entity, $write));
    }

    /**
     * Runs $write (a flush) that may move users between contributors. contributors.user_id is
     * unique and a flush inserts before it updates: the managed contributors whose user changed
     * are released (user_id NULL) first, in the same transaction; the flush then writes their
     * new user, or none, and their timestamps as usual.
     *
     * @template R
     *
     * @param \Closure(): R $write
     *
     * @return R
     */
    public function writeUserLinks(\Closure $write): mixed
    {
        $released = $this->findRelinkedIds();

        if ([] === $released) {
            return $write();
        }

        return $this->getEntityManager()->wrapInTransaction(function () use ($write, $released): mixed {
            $this->createQueryBuilder('c')
                ->update()
                ->set('c.user', ':none')
                ->where('c.id IN (:ids)')
                ->setParameter('none', null)
                ->setParameter('ids', $released)
                ->getQuery()
                ->execute();

            return $write();
        });
    }

    /**
     * Ids of the managed contributors that had a user when loaded and now have another one, or none.
     *
     * @return list<int|string>
     */
    private function findRelinkedIds(): array
    {
        $unitOfWork = $this->getEntityManager()->getUnitOfWork();
        $relinked = [];

        foreach ($unitOfWork->getIdentityMap()[$this->getClassMetadata()->rootEntityName] ?? [] as $contributor) {
            if (!$contributor instanceof ContributorInterface || null === $contributor->getId()) {
                continue;
            }

            $original = $unitOfWork->getOriginalEntityData($contributor)['user'] ?? null;

            if (null !== $original && $original !== $contributor->getUser()) {
                $relinked[] = $contributor->getId();
            }
        }

        return $relinked;
    }

    /**
     * @return list<ContributorInterface>
     */
    public function findAllOrdered(): array
    {
        /** @var list<ContributorInterface> */
        return $this->createQueryBuilder('c')
            ->orderBy('c.lastname', \SortDirection::Ascending)
            ->addOrderBy('c.firstname', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }
}
