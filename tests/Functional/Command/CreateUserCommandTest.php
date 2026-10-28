<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Command;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Entity\Role\RoleInterface;
use Gingerminds\CoreBundle\Entity\User\User;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateUserCommandTest extends KernelTestCase
{
    public function testCreatesTheUserWithAHashedPasswordAndItsContributor(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $role = new Role();
        $role->setName('Member');
        $entityManager->persist($role);
        $entityManager->flush();

        $tester = new CommandTester(new Application(self::$kernel)->find('gingerminds:create:user'));
        $tester->execute([
            '--email' => 'cli@example.com',
            '--role' => 'Member',
            '--lastname' => 'Turing',
            '--firstname' => 'Alan',
            '--password' => 'cli-password',
        ], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());

        $entityManager->clear();
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'cli@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertSame('Turing', $user->getContributor()?->getLastname());
        self::assertSame(['Member'], array_map(static fn (RoleInterface $role): ?string => $role->getName(), $user->getRoleEntities()->toArray()));
        self::assertNotSame('cli-password', $user->getPassword());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'cli-password'));
    }
}
