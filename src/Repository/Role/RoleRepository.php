<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Role;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Entity\Role\RoleInterface;
use Gingerminds\CoreBundle\Repository\AbstractRepository;
use Gingerminds\CoreBundle\Repository\ListQuery;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;

/**
 * @extends AbstractRepository<RoleInterface>
 */
class RoleRepository extends AbstractRepository
{
    /**
     * @param class-string<RoleInterface> $entityClass
     */
    public function __construct(
        ManagerRegistry $registry,
        #[Autowire(param: 'gingerminds_core.resource.role.entity')]
        string $entityClass,
    ) {
        parent::__construct($registry, $entityClass);
    }

    public function findDefaultRole(bool $isExternal = false): ?RoleInterface
    {
        return $this->findOneBy(['default' => true, 'external' => $isExternal]);
    }

    public function findOneByName(string $name): ?RoleInterface
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * @return list<RoleInterface>
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    protected function configureListQueryBuilder(QueryBuilder $qb, ListQuery $query): void
    {
        // Permissions are counted on the list and serialized on reads.
        $qb->leftJoin(self::ALIAS . '.permissions', 'gm_permissions')->addSelect('gm_permissions');
    }

    /**
     * @param RoleInterface $entity
     */
    protected function beforeSave(object $entity, ?FormInterface $form): void
    {
        if (!$entity->isDefault()) {
            return;
        }

        $qb = $this->createQueryBuilder('r')
            ->update()
            ->set('r.default', ':null')
            ->andWhere('r.external = :external')
            ->setParameter('null', null)
            ->setParameter('external', $entity->isExternal());

        if (null !== $entity->getId()) {
            $qb->andWhere('r.id != :id')->setParameter('id', $entity->getId());
        }

        $qb->getQuery()->execute();
    }
}
