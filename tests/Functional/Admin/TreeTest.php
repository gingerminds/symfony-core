<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Tests\Application\Entity\Section;
use Gingerminds\CoreBundle\Tests\Application\Repository\SectionRepository;
use Gingerminds\CoreBundle\Tests\Functional\ApiTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * AbstractTreeCrudController, TreeRepositoryTrait and NoTreeCycle on the `section` resource of the
 * test application.
 */
final class TreeTest extends ApiTestCase
{
    public function testTheIndexRendersTheWholeTree(): void
    {
        $this->client->loginUser($this->fixtures->user('tree-admin@example.com', superAdmin: true), 'admin');
        [$news, $sports] = $this->sections();

        $crawler = $this->client->request('GET', '/admin/sections');

        self::assertResponseIsSuccessful();
        self::assertSame(['News', 'Sports', 'Football', 'Tennis', 'Weather'], $this->labels($crawler));
        self::assertSame((string) $sports->getId(), $crawler->filter('.sortable-item[data-item-id="' . $sports->getId() . '"] > .sortable-level')->attr('data-parent-id'));
        self::assertSame('/admin/sections/reorder', $crawler->filter('[data-controller="gm-sortable-tree"]')->attr('data-gm-sortable-tree-url-value'));
        self::assertSame('/admin/sections/new?parent_id=' . $news->getId(), $crawler->filter('.sortable-item[data-item-id="' . $news->getId() . '"] a[href*="parent_id"]')->attr('href'));
    }

    public function testANewNodeGoesToTheEndOfItsLevel(): void
    {
        $this->client->loginUser($this->fixtures->user('tree-new@example.com', superAdmin: true), 'admin');
        [, $sports] = $this->sections();

        $form = $this->client->request('GET', '/admin/sections/new?parent_id=' . $sports->getId())->filter('form[name="section"]')->form();
        self::assertSame((string) $sports->getId(), $form['section[parent]']->getValue(), '?parent_id= preselects the parent.');
        $form['section[name]'] = 'Rugby';
        $this->client->submit($form);
        self::assertResponseRedirects();

        self::assertSame(2, $this->section('Rugby')->getPosition());
        self::assertSame(['News', 'Sports', 'Football', 'Tennis', 'Rugby', 'Weather'], $this->labels($this->client->request('GET', '/admin/sections')));
    }

    public function testAMovedNodeGoesToTheEndOfItsNewLevel(): void
    {
        $this->client->loginUser($this->fixtures->user('tree-move@example.com', superAdmin: true), 'admin');
        [$news] = $this->sections();
        $tennis = $this->section('Tennis');

        $form = $this->client->request('GET', '/admin/sections/' . $tennis->getId() . '/edit')->filter('form[name="section"]')->form();
        $form['section[parent]'] = (string) $news->getId();
        $this->client->submit($form);
        self::assertResponseRedirects();

        self::assertSame(['News', 'Tennis', 'Sports', 'Football', 'Weather'], $this->labels($this->client->request('GET', '/admin/sections')));
    }

    public function testReorderAppliesTheOrderOfOneLevel(): void
    {
        $this->client->loginUser($this->fixtures->user('tree-reorder@example.com', superAdmin: true), 'admin');
        [$news, $sports, $weather] = $this->sections();
        $football = $this->section('Football');
        $token = $this->reorderToken();

        // Football belongs to another level: ignored.
        $this->reorder(['ids' => [$weather->getId(), $football->getId(), $news->getId(), $sports->getId()], 'parent_id' => null], $token);
        self::assertResponseIsSuccessful();

        self::assertSame(['Weather', 'News', 'Sports', 'Football', 'Tennis'], $this->labels($this->client->request('GET', '/admin/sections')));
    }

    public function testReorderNeedsTheTokenAndTheEditPermission(): void
    {
        $this->client->loginUser($this->fixtures->user('tree-csrf@example.com', superAdmin: true), 'admin');
        [$news, $sports] = $this->sections();

        $this->reorder(['ids' => [$sports->getId(), $news->getId()]], 'wrong');
        self::assertFalse($this->client->getResponse()->isSuccessful());

        $this->client->loginUser($this->fixtures->user('tree-viewer@example.com', ['view sections']), 'admin');
        $crawler = $this->client->request('GET', '/admin/sections');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-controller="gm-sortable-tree"]'), 'No drag & drop without the EDIT permission.');

        $this->reorder(['ids' => [$sports->getId(), $news->getId()]], 'any');
        self::assertResponseStatusCodeSame(403);
        self::assertSame(['News', 'Sports', 'Football', 'Tennis', 'Weather'], $this->labels($this->client->request('GET', '/admin/sections')));
    }

    public function testANodeCannotBeMovedUnderItsOwnDescendant(): void
    {
        $this->client->loginUser($this->fixtures->user('tree-cycle@example.com', superAdmin: true), 'admin');
        [, $sports] = $this->sections();
        $football = $this->section('Football');

        $form = $this->client->request('GET', '/admin/sections/' . $sports->getId() . '/edit')->filter('form[name="section"]')->form();
        $form['section[parent]'] = (string) $football->getId();
        $crawler = $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('cannot be placed under itself', $crawler->filter('form[name="section"]')->text());
    }

    public function testDescendantIds(): void
    {
        [$news, $sports] = $this->sections();
        $repository = self::getContainer()->get(SectionRepository::class);
        $ids = $repository->findIdsWithDescendants([$sports->getId(), 999999]);
        sort($ids);

        self::assertSame([$sports->getId(), $this->section('Football')->getId(), $this->section('Tennis')->getId()], $ids);
        self::assertSame([$news->getId()], $repository->findIdsWithDescendants([$news->getId()]));
    }

    /**
     * News, Sports (Football, Tennis), Weather, saved one by one: each goes to the end of its level.
     *
     * @return array{Section, Section, Section}
     */
    private function sections(): array
    {
        $repository = self::getContainer()->get(SectionRepository::class);
        $news = new Section('News');
        $sports = new Section('Sports');
        $weather = new Section('Weather');

        foreach ([$news, $sports, new Section('Football', $sports), new Section('Tennis', $sports), $weather] as $section) {
            $repository->save($section);
        }

        return [$news, $sports, $weather];
    }

    private function section(string $name): Section
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return $entityManager->getRepository(Section::class)->findOneBy(['name' => $name]) ?? throw new \LogicException($name);
    }

    private function reorderToken(): string
    {
        return (string) $this->client->request('GET', '/admin/sections')
            ->filter('[data-controller="gm-sortable-tree"]')
            ->attr('data-gm-sortable-tree-csrf-token-value');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function reorder(array $payload, string $token): void
    {
        $this->client->request('POST', '/admin/sections/reorder', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $token,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    /**
     * The node labels, depth-first.
     *
     * @return list<string>
     */
    private function labels(Crawler $crawler): array
    {
        return $crawler->filter('.sortable-item > .tree-item-row > span')->each(static fn (Crawler $label): string => trim($label->text()));
    }
}
