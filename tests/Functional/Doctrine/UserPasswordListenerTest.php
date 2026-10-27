<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Entity\User\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The plain password is hashed on flush, for a new user and for a managed user whose only
 * change is its (unmapped) plain password.
 */
final class UserPasswordListenerTest extends KernelTestCase
{
    public function testPlainPasswordIsHashedOnInsertAndOnUpdate(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('hash@example.com');
        $user->setPlainPassword('first-password');
        $entityManager->persist($user);
        $entityManager->flush();

        self::assertNull($user->getPlainPassword());
        self::assertTrue($hasher->isPasswordValid($user, 'first-password'));

        $entityManager->clear();
        $user = $entityManager->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $user);
        $user->setPlainPassword('second-password');
        $entityManager->flush();
        $entityManager->clear();

        $user = $entityManager->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($hasher->isPasswordValid($user, 'second-password'));
    }

    public function testNoPlainPasswordKeepsThePassword(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('keep@example.com');
        $user->setPassword('existing-hash');
        $entityManager->persist($user);
        $entityManager->flush();

        self::assertSame('existing-hash', $user->getPassword());
    }
}
