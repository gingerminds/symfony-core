<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional;

use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Entity\Security\ApiToken;
use Gingerminds\CoreBundle\Entity\User\BaseUser;
use Gingerminds\CoreBundle\Entity\User\Contributor;
use Gingerminds\CoreBundle\Entity\User\User as CoreUser;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\CoreBundle\Tests\Application\Kernel;
use Gingerminds\CoreBundle\Tests\Application\Override\User;
use Gingerminds\CoreBundle\Tests\Application\Override\UserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

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

    public function testTheResourceVoterTargetsTheProjectEntity(): void
    {
        $decisionManager = self::$kernel->getContainer()->get('test.service_container')->get('security.access.decision_manager');
        $user = new User();
        $user->setEmail('voter@example.com');
        new \ReflectionProperty(BaseUser::class, 'id')->setValue($user, 42);
        $token = new UsernamePasswordToken($user, 'admin', $user->getRoles());

        // The user's own account: granted by the core ResourceVoter on the project class.
        self::assertTrue($decisionManager->decide($token, ['VIEW'], $user));
        self::assertFalse($decisionManager->decide($token, ['DELETE'], new User()));
        self::assertFalse($decisionManager->decide($token, ['CREATE'], 'user'));
    }

    /**
     * The bundle write behavior applies to the project entity: native deserialization with
     * relations by id, validation (password required on creation), password hashing and
     * contributor link written by the repository hooks.
     */
    public function testTheProjectUserIsWrittenThroughTheApi(): void
    {
        $entityManager = $this->resetSchema();

        $superAdmin = new Role();
        $superAdmin->setName(UserInterface::SUPER_ADMIN_ROLE);
        $member = new Role();
        $member->setName('Member');
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->addRoleEntity($superAdmin);
        $token = ApiToken::generatePlainToken();

        foreach ([$superAdmin, $member, $admin, new ApiToken($admin, 'test', $token)] as $entity) {
            $entityManager->persist($entity);
        }

        $entityManager->flush();

        $client = new KernelBrowser(self::$kernel);
        $server = ['HTTP_ACCEPT' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'];

        $client->request('POST', '/api/users', server: $server, content: json_encode(['email' => 'no-password@example.com'], \JSON_THROW_ON_ERROR));
        self::assertSame(422, $client->getResponse()->getStatusCode());

        $client->request('POST', '/api/users', server: $server, content: json_encode([
            'email' => 'project@example.com',
            'plainPassword' => 'secret-password',
            'phone' => '0102030405',
            'roles' => [$member->getId()],
            'contributor' => ['firstname' => 'Ada', 'lastname' => 'Lovelace'],
        ], \JSON_THROW_ON_ERROR));

        self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('0102030405', $data['phone']);
        self::assertSame('Member', $data['roles'][0]['name']);
        self::assertSame('Lovelace', $data['contributor']['lastname']);

        $entityManager->clear();
        $user = $entityManager->getRepository(User::class)->find($data['id']);
        self::assertInstanceOf(User::class, $user);
        self::assertNotNull($user->getPassword());
        self::assertNotSame('secret-password', $user->getPassword());
        self::assertSame('Lovelace', $user->getContributor()?->getLastname());
    }

    /**
     * The project profile form (`profile.form`) on the bundle profile page: its extra field is
     * rendered by the bundle template and saved.
     */
    public function testTheProfilePageUsesTheProjectForm(): void
    {
        $entityManager = $this->resetSchema();
        $user = new User();
        $user->setEmail('profile@example.com');
        $entityManager->persist($user);
        $entityManager->flush();

        $client = new KernelBrowser(self::$kernel);
        $client->loginUser($user, 'admin');

        $crawler = $client->request('GET', '/admin/profile');
        $form = $crawler->filter('form[name="profile"]')->form();
        $form['profile[phone]'] = '0102030405';
        $client->submit($form);

        self::assertSame(303, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $entityManager->clear();
        self::assertSame('0102030405', $entityManager->getRepository(User::class)->findOneBy(['email' => 'profile@example.com'])?->getPhone());
    }

    /**
     * The `repositoryClass` of the project entity is the one of the authentication: the API
     * login and the session user provider sign in by phone through it.
     */
    public function testTheAuthenticationUsesTheProjectRepository(): void
    {
        $container = self::$kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(UserRepository::class, $container->get('gingerminds_core.repository.user'));

        $entityManager = $this->resetSchema();
        $user = new User();
        $user->setEmail('phone@example.com');
        $user->setPhone('0102030405');
        $user->setPlainPassword('secret-password');
        $entityManager->persist($user);
        $entityManager->flush();

        $client = new KernelBrowser(self::$kernel);
        $client->request('POST', '/api/login', server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => '0102030405',
            'password' => 'secret-password',
        ], \JSON_THROW_ON_ERROR));

        self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertSame('phone@example.com', $container->get('gingerminds_core.security.user_provider')->loadUserByIdentifier('0102030405')->getUserIdentifier());
    }

    private function resetSchema(): EntityManagerInterface
    {
        // The kernel is shared by the tests: no entity of a previous test in the identity map.
        $entityManager = $this->entityManager();
        $entityManager->clear();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::$kernel->getContainer()->get('doctrine')->getManager();
    }
}
