<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Security;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Entity\Security\ApiToken;
use Gingerminds\CoreBundle\Entity\User\UserInterface;

/**
 * @extends ServiceEntityRepository<ApiToken>
 */
class ApiTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiToken::class);
    }

    /**
     * Creates and stores a token; returns the plain value, only available now.
     */
    public function createToken(UserInterface $user, string $name = 'api-token', ?\DateTimeImmutable $expiresAt = null): string
    {
        $plainToken = ApiToken::generatePlainToken();

        $this->getEntityManager()->persist(new ApiToken($user, $name, $plainToken, $expiresAt));
        $this->getEntityManager()->flush();

        return $plainToken;
    }

    public function findOneByPlainToken(string $plainToken): ?ApiToken
    {
        return $this->createQueryBuilder('t')
            ->addSelect('u')
            ->join('t.user', 'u')
            ->andWhere('t.tokenHash = :hash')
            ->setParameter('hash', ApiToken::hash($plainToken))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function markUsed(ApiToken $token, \DateTimeImmutable $at): void
    {
        $token->markUsed($at);
        $this->getEntityManager()->flush();
    }

    public function revoke(string $plainToken): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.tokenHash = :hash')
            ->setParameter('hash', ApiToken::hash($plainToken))
            ->getQuery()
            ->execute();
    }

    public function revokeAll(UserInterface $user): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
