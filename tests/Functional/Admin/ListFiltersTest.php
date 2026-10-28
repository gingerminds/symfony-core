<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Tests\Application\Entity\Category;
use Gingerminds\CoreBundle\Tests\Application\Entity\Product;
use Gingerminds\CoreBundle\Tests\Application\Enum\ProductStatus;
use Gingerminds\CoreBundle\Tests\Functional\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The admin lists read the list parameters of the API collections (`order[...]`, `search`,
 * `#[Filter]` properties, `page`, `itemsPerPage`) and apply the same API Platform filters: same
 * query, same rows. The panel, the sort links and the pagination links keep them.
 */
final class ListFiltersTest extends ApiTestCase
{
    private string $token;

    private Category $computers;

    private Category $tag;

    protected function setUp(): void
    {
        parent::setUp();

        $user = $this->fixtures->user('admin-list@example.com', superAdmin: true);
        $this->token = $this->fixtures->token($user);
        $this->client->loginUser($user, 'admin');
        $this->createCatalog();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function queries(): iterable
    {
        yield 'no parameter' => [''];
        yield 'sort through a relation' => ['order[category.name]=desc'];
        yield 'search' => ['search=accessor&order[name]=asc'];
        yield 'number' => ['price[gte]=100&price[lte]=900'];
        yield 'date, display timezone' => ['publishedAt[after]=2024-03-01&publishedAt[before]=2024-12-31'];
        yield 'boolean false includes null' => ['active=false&order[name]=desc'];
        yield 'enum, multiple' => ['status[]=draft&status[]=Archived'];
        yield 'exact' => ['name=Phone'];
        yield 'to-one relation, id' => ['category=__COMPUTERS__'];
        yield 'to-one relation, none' => ['category=null'];
        yield 'to-many relation, ids' => ['tags[]=__TAG__'];
        yield 'combined' => ['status=published&active=false&price[gte]=100'];
        yield 'page' => ['order[name]=asc&itemsPerPage=2&page=2'];
    }

    #[DataProvider('queries')]
    public function testSameParametersSameRowsAsTheApi(string $query): void
    {
        $query = $this->ids($query);
        $api = array_column($this->api('GET', '/api/products?' . $query, $this->token)['member'], 'name');
        $this->assertStatus(200);

        self::assertSame($api, $this->adminNames($this->client->request('GET', $this->listUrl() . '?' . $query)));
    }

    public function testEmptyPanelFieldsAreNotFilters(): void
    {
        $crawler = $this->client->request('GET', $this->listUrl() . '?search=&price[gte]=&price[lte]=&publishedAt[after]=&active=&status=&category=&order[name]=asc');

        self::assertResponseIsSuccessful();
        self::assertSame(['Cable', 'Laptop', 'Mouse', 'Phone', 'Tablet'], $this->adminNames($crawler));
    }

    public function testThePanelFieldsAreNamedAfterTheParameters(): void
    {
        $crawler = $this->client->request('GET', $this->listUrl() . '?order[name]=desc&itemsPerPage=25&status[]=draft');
        $form = $crawler->filter('#gm-filters form');
        $names = $form->filter('input[name], select[name]')->each(static fn (Crawler $field): string => (string) $field->attr('name'));

        foreach (['itemsPerPage', 'order[name]', 'search', 'price[gte]', 'price[lte]', 'active', 'status[]', 'publishedAt[after]', 'publishedAt[before]', 'category', 'tags[]'] as $name) {
            self::assertContains($name, $names);
        }

        // `#[Filter(admin: false)]`: API only.
        self::assertNotContains('name', $names);
        self::assertSame('draft', $form->filter('select[name="status[]"] option[selected]')->attr('value'));
        self::assertSame('desc', $form->filter('input[name="order[name]"]')->attr('value'));
    }

    public function testSortAndPaginationLinksKeepTheFilters(): void
    {
        $crawler = $this->client->request('GET', $this->listUrl() . '?status=draft&order[name]=asc&itemsPerPage=1');
        $base = $this->listUrl();

        self::assertSame(['name', 'category.name'], $crawler->filter('th.sortable')->each(static fn (Crawler $th): ?string => $th->attr('data-sort')));
        self::assertSame($base . '?status=draft&order%5Bname%5D=desc&itemsPerPage=1', $crawler->filter('th[data-sort="name"] a')->attr('href'));
        self::assertSame($base . '?status=draft&order%5Bcategory.name%5D=asc&itemsPerPage=1', $crawler->filter('th[data-sort="category.name"] a')->attr('href'));
        self::assertSame('http://localhost' . $base . '?status=draft&order%5Bname%5D=asc&itemsPerPage=1&page=2', $crawler->filter('head link[rel="next"]')->attr('href'));
        self::assertSame($base . '?status=draft&order%5Bname%5D=asc&itemsPerPage=1&page=2', $crawler->filter('.pagination a.page-link')->eq(3)->attr('href'));
        self::assertSame(['Cable'], $this->adminNames($crawler));
    }

    /**
     * @return list<string>
     */
    private function adminNames(Crawler $crawler): array
    {
        self::assertResponseIsSuccessful();

        return $crawler->filter('#gm-list-table tbody tr td:first-child')->each(static fn (Crawler $td): string => trim($td->text()));
    }

    private function listUrl(): string
    {
        return '/admin/categories/' . $this->computers->getId() . '/products';
    }

    private function ids(string $query): string
    {
        return strtr($query, ['__COMPUTERS__' => (string) $this->computers->getId(), '__TAG__' => (string) $this->tag->getId()]);
    }

    private function createCatalog(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->computers = new Category('Computers');
        $accessories = new Category('Accessories');
        $this->tag = new Category('Tag');

        foreach ([$this->computers, $accessories, $this->tag] as $category) {
            $entityManager->persist($category);
        }

        $rows = [
            ['Laptop', 1500, true, ProductStatus::Published, '2024-03-01', $this->computers, true],
            ['Tablet', 600, null, ProductStatus::Archived, '2023-06-15', $this->computers, true],
            ['Phone', 800, false, ProductStatus::Published, '2024-09-10', null, false],
            ['Mouse', 25, true, ProductStatus::Draft, null, $accessories, false],
            ['Cable', 0, false, ProductStatus::Draft, '2025-01-05', $accessories, false],
        ];

        foreach ($rows as [$name, $price, $active, $status, $publishedAt, $category, $tagged]) {
            $product = new Product()
                ->setName($name)
                ->setPrice($price)
                ->setActive($active)
                ->setStatus($status)
                ->setPublishedAt(null !== $publishedAt ? new \DateTimeImmutable($publishedAt) : null)
                ->setCategory($category);

            if ($tagged) {
                $product->addTag($this->tag);
            }

            $entityManager->persist($product);
        }

        $entityManager->flush();
    }
}
