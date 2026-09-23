<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional;

use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Gingerminds\CoreBundle\Entity\User\Contributor;
use Gingerminds\CoreBundle\Entity\User\User as CoreUser;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\CoreBundle\Tests\Application\Kernel;
use Gingerminds\CoreBundle\Tests\Application\Override\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A project overriding the bundle User entity (`override` environment).
 */
final class EntityOverrideTest extends TestCase
{
    private static Kernel $kernel;

    public static function setUpBeforeClass(): void
    {
        self::$kernel = new Kernel('override', true);
        new Filesystem()->remove(self::$kernel->getCacheDir());
        self::$kernel->boot();
    }

    public static function tearDownAfterClass(): void
    {
        self::$kernel->shutdown();
    }

    public function testOnlyTheProjectEntityIsMapped(): void
    {
        $entityManager = $this->entityManager();
        $classes = $entityManager->getMetadataFactory()->getAllMetadata();
        $entities = array_map(static fn (ClassMetadata $metadata): string => $metadata->getName(), array_filter($classes, static fn (ClassMetadata $metadata): bool => !$metadata->isMappedSuperclass));

        self::assertContains(User::class, $entities);
        self::assertNotContains(CoreUser::class, $entities);
        self::assertContains(Contributor::class, $entities, 'Other bundle entities stay mapped.');
        self::assertSame('users', $entityManager->getClassMetadata(User::class)->getTableName());
        self::assertTrue($entityManager->getClassMetadata(User::class)->hasField('phone'));
    }

    public function testRelationsTargetTheProjectEntity(): void
    {
        $entityManager = $this->entityManager();

        self::assertSame(User::class, $entityManager->getClassMetadata(Contributor::class)->getAssociationTargetClass('user'));
        self::assertSame(User::class, $entityManager->getClassMetadata(UserInterface::class)->getName());
        self::assertSame(User::class, self::$kernel->getContainer()->get('test.service_container')->get(ResourceRegistry::class)->getEntityClass('user'));
    }

    public function testOnlyTheProjectEntityIsAnApiResource(): void
    {
        /** @var ResourceNameCollectionFactoryInterface $factory */
        $factory = self::$kernel->getContainer()->get('test.service_container')->get('api_platform.metadata.resource.name_collection_factory');
        $resources = iterator_to_array($factory->create());

        self::assertContains(User::class, $resources);
        self::assertNotContains(CoreUser::class, $resources);
        self::assertContains(Contributor::class, $resources);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::$kernel->getContainer()->get('doctrine')->getManager();
    }
}
