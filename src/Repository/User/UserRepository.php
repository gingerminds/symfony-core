<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\User;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Entity\User\ContributorInterface;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Enum\Civility;
use Gingerminds\CoreBundle\Repository\AbstractRepository;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends AbstractRepository<UserInterface>
 */
class UserRepository extends AbstractRepository implements UserLoaderInterface, PasswordUpgraderInterface
{
    public const string NEW_CONTRIBUTOR = '__new__';

    /**
     * @param class-string<UserInterface>        $entityClass
     * @param class-string<ContributorInterface> $contributorClass
     */
    public function __construct(
        ManagerRegistry $registry,
        #[Autowire(param: 'gingerminds_core.resource.user.entity')]
        string $entityClass,
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire(param: 'gingerminds_core.resource.contributor.entity')]
        private readonly string $contributorClass,
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

    /**
     * @param UserInterface $entity
     */
    protected function beforeSave(object $entity, ?FormInterface $form): void
    {
        $plainPassword = $entity->getPlainPassword();

        if (null !== $plainPassword && '' !== $plainPassword) {
            $entity->setPassword($this->passwordHasher->hashPassword($entity, $plainPassword));
            $entity->setPlainPassword(null);
        }

        if ($form instanceof FormInterface && ($form->has('contributorId') || $form->has('contributorLastname'))) {
            $this->handleContributor($entity, $form);
        }
    }

    /**
     * @param FormInterface<mixed> $form
     */
    protected function handleContributor(UserInterface $user, FormInterface $form): void
    {
        $selector = $form->has('contributorId')
            ? $form->get('contributorId')->getData()
            : ($user->getContributor()?->getId() ?? self::NEW_CONTRIBUTOR);

        if (null === $selector || '' === $selector) {
            return;
        }

        $current = $user->getContributor();
        $contributor = self::NEW_CONTRIBUTOR === $selector
            ? new ($this->contributorClass)()
            : $this->getEntityManager()->find($this->contributorClass, $selector);

        if (!$contributor instanceof ContributorInterface) {
            return;
        }

        if ($current instanceof ContributorInterface && $current !== $contributor) {
            $current->setUser(null);
        }

        $this->fillContributor($contributor, $form);
        $contributor->setUser($user);
        $user->setContributor($contributor);

        $this->getEntityManager()->persist($contributor);
    }

    /**
     * @param FormInterface<mixed> $form
     */
    protected function fillContributor(ContributorInterface $contributor, FormInterface $form): void
    {
        $value = static fn (string $field): mixed => $form->has($field) ? $form->get($field)->getData() : null;

        if (null !== $firstname = $value('contributorFirstname')) {
            $contributor->setFirstname((string) $firstname);
        }

        if (null !== $lastname = $value('contributorLastname')) {
            $contributor->setLastname((string) $lastname);
        }

        $contributor->setFirstname($contributor->getFirstname() ?? '');
        $contributor->setLastname($contributor->getLastname() ?? '');

        if (null !== $trigram = $value('contributorTrigram')) {
            $contributor->setTrigram((string) $trigram);
        }

        $civility = $value('contributorCivility');

        if ($civility instanceof Civility) {
            $contributor->setCivility($civility);
        }
    }
}
