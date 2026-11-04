<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Command;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Entity\Security\ApiToken;
use Gingerminds\CoreBundle\Entity\User\User;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgeApiTokensCommandTest extends KernelTestCase
{
    public function testDeletesTheExpiredTokensOnly(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = new User();
        $user->setEmail('tokens@example.com');
        $entityManager->persist($user);

        foreach (['expired' => '-1 minute', 'valid' => '+1 hour', 'endless' => null] as $name => $expiresAt) {
            $entityManager->persist(new ApiToken($user, $name, ApiToken::generatePlainToken(), null !== $expiresAt ? new \DateTimeImmutable($expiresAt) : null));
        }

        $entityManager->flush();

        $tester = new CommandTester(new Application(self::$kernel)->find('gingerminds:api-token:purge'));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('1 expired API token(s) deleted.', $tester->getDisplay());

        $entityManager->clear();
        $names = array_map(static fn (ApiToken $token): string => $token->getName(), $entityManager->getRepository(ApiToken::class)->findAll());
        sort($names);
        self::assertSame(['endless', 'valid'], $names);
    }
}
