<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Entity\User\Contributor;
use Gingerminds\CoreBundle\Entity\User\User;
use Gingerminds\CoreBundle\Repository\User\UserRepository;
use Gingerminds\CoreBundle\Tests\Functional\ApiTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The user / contributor pickers of the admin forms are searched remotely:
 * rendering a form never loads the whole users or contributors table.
 */
final class RemoteAutocompleteFieldTest extends ApiTestCase
{
    public function testContributorFormQueryCountDoesNotDependOnTheUsers(): void
    {
        $this->client->disableReboot();
        $this->client->loginUser($this->fixtures->user('picker-0@example.com', superAdmin: true), 'admin');
        $this->fixtures->user('picker-1@example.com');
        $few = $this->queryCount('/admin/contributors/new');

        foreach (range(2, 6) as $i) {
            $this->fixtures->user('picker-' . $i . '@example.com');
        }

        self::assertSame($few, $this->queryCount('/admin/contributors/new'));

        $select = $this->client->getCrawler()->filter('select[name="contributor[user]"]');
        self::assertSame('/admin/_autocomplete/user', $this->autocompleteUrl($select));
        self::assertSame([''], $select->filter('option')->extract(['value']));
    }

    public function testUserFormQueryCountDoesNotDependOnTheContributors(): void
    {
        $this->client->disableReboot();
        $this->client->loginUser($this->fixtures->user('selector-0@example.com', superAdmin: true), 'admin');
        $this->fixtures->user('selector-1@example.com');
        $few = $this->queryCount('/admin/users/new');

        foreach (range(2, 6) as $i) {
            $this->fixtures->user('selector-' . $i . '@example.com');
        }

        self::assertSame($few, $this->queryCount('/admin/users/new'));

        $select = $this->client->getCrawler()->filter('select[name="user[contributorId]"]');
        self::assertSame('/admin/_autocomplete/contributor', $this->autocompleteUrl($select));
        self::assertSame(['', UserRepository::NEW_CONTRIBUTOR], $select->filter('option')->extract(['value']));
    }

    public function testContributorFormSavesARemotelyChosenUser(): void
    {
        $this->client->loginUser($this->fixtures->user('chooser@example.com', superAdmin: true), 'admin');
        $user = $this->bareUser('chosen@example.com');

        $form = $this->client->request('GET', '/admin/contributors/new')->filter('form[name="contributor"]')->form();
        // The option is not rendered: Tom Select adds it from the autocomplete results.
        $form->disableValidation();
        $form['contributor[lastname]'] = 'Chosen';
        $form['contributor[firstname]'] = 'Remote';
        $form['contributor[user]'] = (string) $user->getId();
        $this->client->submit($form);

        self::assertResponseRedirects();
        $contributor = $this->entityManager()->getRepository(Contributor::class)->findOneBy(['lastname' => 'Chosen']);
        self::assertSame($user->getId(), $contributor?->getUser()?->getId());

        // The edit form preloads the selected user only.
        $crawler = $this->client->request('GET', '/admin/contributors/' . $contributor->getId() . '/edit');
        self::assertSame(['chosen@example.com'], $crawler->filter('select[name="contributor[user]"] option[selected]')->extract(['_text']));
    }

    public function testUserFormLinksARemotelyChosenContributor(): void
    {
        $this->client->loginUser($this->fixtures->user('linker@example.com', superAdmin: true), 'admin');
        $contributor = new Contributor();
        $contributor->setFirstname('Free');
        $contributor->setLastname('Agent');
        $entityManager = $this->entityManager();
        $entityManager->persist($contributor);
        $entityManager->flush();

        $form = $this->client->request('GET', '/admin/users/new')->filter('form[name="user"]')->form();
        $form->disableValidation();
        $form['user[email]'] = 'linked@example.com';
        $form['user[password][first]'] = 'password123';
        $form['user[password][second]'] = 'password123';
        $form['user[contributorId]'] = (string) $contributor->getId();
        $this->client->submit($form);

        self::assertResponseRedirects();
        $user = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'linked@example.com']);
        self::assertSame($contributor->getId(), $user?->getContributor()?->getId());

        $crawler = $this->client->request('GET', '/admin/users/' . $user->getId() . '/edit');
        self::assertSame(
            [(string) $contributor->getId()],
            $crawler->filter('select[name="user[contributorId]"] option[selected]')->extract(['value']),
        );
    }

    public function testUnknownContributorIsRejected(): void
    {
        $this->client->loginUser($this->fixtures->user('unknown@example.com', superAdmin: true), 'admin');

        $form = $this->client->request('GET', '/admin/users/new')->filter('form[name="user"]')->form();
        $form->disableValidation();
        $form['user[email]'] = 'nobody@example.com';
        $form['user[password][first]'] = 'password123';
        $form['user[password][second]'] = 'password123';
        $form['user[contributorId]'] = '999999999';
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
    }

    private function queryCount(string $uri): int
    {
        $this->entityManager()->clear();
        $queries = self::getContainer()->get('doctrine.debug_data_holder');
        $queries->reset();

        $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful();

        return \count($queries->getData()['default'] ?? []);
    }

    private function autocompleteUrl(Crawler $select): ?string
    {
        self::assertCount(1, $select);

        return $select->attr('data-symfony--ux-autocomplete--autocomplete-url-value');
    }

    private function bareUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('not-used');
        $entityManager = $this->entityManager();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
