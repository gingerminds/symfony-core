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
class ContributorRepository extends AbstractRepository
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
     * @return list<ContributorInterface>
     */
    public function findAllOrdered(): array
    {
        /** @var list<ContributorInterface> */
        return $this->createQueryBuilder('c')
            ->orderBy('c.lastname', 'ASC')
            ->addOrderBy('c.firstname', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
