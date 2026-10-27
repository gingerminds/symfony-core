<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\User;

use Doctrine\Persistence\ManagerRegistry;
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
