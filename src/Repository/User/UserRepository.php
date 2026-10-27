<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\User;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Entity\User\ContributorInterface;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Repository\AbstractRepository;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends AbstractRepository<UserInterface>
 */
class UserRepository extends AbstractRepository implements UserLoaderInterface, PasswordUpgraderInterface
{
    /**
     * @param class-string<UserInterface> $entityClass
     */
    public function __construct(
        ManagerRegistry $registry,
        #[Autowire(param: 'gingerminds_core.resource.user.entity')]
        string $entityClass,
    ) {
        parent::__construct($registry, $entityClass);
    }

    /**
     * Saves the user, a contributor change included (UserType: a new contributor or another
     * existing one). contributors.user_id is unique and a flush inserts before it updates: the
     * contributor the user leaves is released (user_id NULL) first, in the same transaction.
     * It is kept, without user; the flush then writes its change (and its timestamps) as usual.
     */
    public function save(object $entity, bool $flush = true): void
    {
        $released = $flush ? $this->findReleasedContributorIds($entity) : [];

        if ([] === $released) {
            parent::save($entity, $flush);

            return;
        }

        $this->getEntityManager()->wrapInTransaction(function () use ($entity, $released): void {
            $this->getEntityManager()->createQueryBuilder()
                ->update($this->getClassMetadata()->getAssociationTargetClass('contributor'), 'c')
                ->set('c.user', ':none')
                ->where('c.id IN (:ids)')
                ->setParameter('none', null)
                ->setParameter('ids', $released)
                ->getQuery()
                ->execute();

            parent::save($entity);
        });
    }

    /**
     * Ids of the managed contributors that were the user's when loaded and no longer are.
     *
     * @return list<int|string>
     */
    private function findReleasedContributorIds(UserInterface $user): array
    {
        if (null === $user->getId()) {
            return [];
        }

        $unitOfWork = $this->getEntityManager()->getUnitOfWork();
        $contributorClass = $this->getEntityManager()->getClassMetadata($this->getClassMetadata()->getAssociationTargetClass('contributor'));
        $released = [];

        foreach ($unitOfWork->getIdentityMap()[$contributorClass->rootEntityName] ?? [] as $contributor) {
            if (
                $contributor instanceof ContributorInterface
                && null !== $contributor->getId()
                && $contributor->getUser() !== $user
                && ($unitOfWork->getOriginalEntityData($contributor)['user'] ?? null) === $user
            ) {
                $released[] = $contributor->getId();
            }
        }

        return $released;
    }

    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        /** @var UserInterface|null */
        return $this->createQueryBuilder('u')
            ->leftJoin('u.roleEntities', 'r')->addSelect('r')
            ->leftJoin('r.permissions', 'p')->addSelect('p')
            ->leftJoin('u.contributor', 'c')->addSelect('c')
            ->andWhere('LOWER(u.email) = :email')
            ->setParameter('email', mb_strtolower($identifier))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findForAuthentication(int|string $id): ?UserInterface
    {
        /** @var UserInterface|null */
        return $this->createQueryBuilder('u')
            ->leftJoin('u.roleEntities', 'r')->addSelect('r')
            ->leftJoin('r.permissions', 'p')->addSelect('p')
            ->leftJoin('u.contributor', 'c')->addSelect('c')
            ->andWhere('u.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof UserInterface) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }
}
