<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Api;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Tests\Application\Entity\Category;
use Gingerminds\CoreBundle\Tests\Application\Entity\Product;
use Gingerminds\CoreBundle\Tests\Application\Enum\ProductStatus;
use Gingerminds\CoreBundle\Tests\Functional\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Pagination, sorting, search and every core filter type of the API collections: the native
 * Doctrine provider with the list parameters of the entity attributes.
 */
final class ListTest extends ApiTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->fixtures->token($this->fixtures->user('root@example.com', superAdmin: true));
        $this->createCatalog();
    }

    public function testHydraPagination(): void
    {
        $data = $this->api('GET', '/api/products?itemsPerPage=2&page=2', $this->token);

        $this->assertStatus(200);
        self::assertSame(5, $data['totalItems']);
        self::assertCount(2, $data['member']);
        self::assertStringContainsString('page=1', $data['view']['previous']);
        self::assertStringContainsString('page=3', $data['view']['next']);
        self::assertStringContainsString('page=3', $data['view']['last']);
    }

    public function testClientItemsPerPage(): void
    {
        $data = $this->api('GET', '/api/products?itemsPerPage=1', $this->token);

        self::assertCount(1, $data['member']);
        self::assertStringContainsString('page=5', $data['view']['last']);
    }

    public function testSortingThroughARelation(): void
    {
        $data = $this->api('GET', '/api/products?order[category.name]=desc', $this->token);

        // Computers, Accessories, then no category; ties by id.
        self::assertSame(['Laptop', 'Tablet', 'Mouse', 'Cable', 'Phone'], $this->names($data));
    }

    public function testAMappedFieldWithoutSortableIsIgnored(): void
    {
        // `price` and `category.id` are mapped, not `#[Sortable]`: id order.
        foreach (['price', 'category.id'] as $path) {
            $data = $this->api('GET', '/api/products?order[' . $path . ']=desc', $this->token);

            $this->assertStatus(200);
            self::assertSame(['Laptop', 'Tablet', 'Phone', 'Mouse', 'Cable'], $this->names($data), $path);
        }
    }

    public function testUnknownSortPropertyIsIgnoredAndAWrongDirectionRefused(): void
    {
        $this->api('GET', '/api/products?order[unknown); DROP TABLE products; --]=desc', $this->token);
        $this->assertStatus(200);

        // A declared parameter is validated by API Platform (SortFilter schema: asc|desc).
        $this->api('GET', '/api/products?order[name]=sideways', $this->token);
        $this->assertStatus(422);
    }

    public function testSearchMatchesOwnAndRelatedFields(): void
    {
        self::assertSame(['Laptop'], $this->names($this->api('GET', '/api/products?search=LAP&order[name]=asc', $this->token)));
        self::assertSame(['Cable', 'Mouse'], $this->names($this->api('GET', '/api/products?search=accessor&order[name]=asc', $this->token)));
        // A product without category is still found by its own fields (LEFT JOIN).
        self::assertSame(['Phone'], $this->names($this->api('GET', '/api/products?search=pho', $this->token)));
    }

    public function testAFieldWithoutSearchableIsNotSearched(): void
    {
        // `status` (Archived: Tablet) and `tags.name` (Tag: Laptop, Tablet) are not `#[Searchable]`.
        foreach (['archived', 'tag'] as $search) {
            $data = $this->api('GET', '/api/products?search=' . $search, $this->token);

            $this->assertStatus(200);
            self::assertSame([], $this->names($data), $search);
        }
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('filters')]
    public function testFilters(string $query, array $expected): void
    {
        $data = $this->api('GET', '/api/products?order[name]=asc&' . $query, $this->token);

        $this->assertStatus(200);
        self::assertSame($expected, $this->names($data));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function filters(): iterable
    {
        yield 'number range' => ['price[gte]=100&price[lte]=900', ['Phone', 'Tablet']];
        yield 'number zero bound' => ['price[lte]=0', ['Cable']];
        yield 'number strict bound' => ['price[gt]=600', ['Laptop', 'Phone']];
        yield 'date range' => ['publishedAt[after]=2024-01-01&publishedAt[before]=2024-12-31', ['Laptop', 'Phone']];
        // 2025-01-05 00:30 UTC is still 2025-01-05 in Paris, 2024-03-01 00:00 UTC is 2024-03-01.
        yield 'date single day, display timezone' => ['publishedAt[after]=2024-03-01&publishedAt[before]=2024-03-01', ['Laptop']];
        yield 'boolean true' => ['active=true', ['Laptop', 'Mouse']];
        yield 'boolean false includes null' => ['active=false', ['Cable', 'Phone', 'Tablet']];
        yield 'boolean 0' => ['active=0', ['Cable', 'Phone', 'Tablet']];
        yield 'boolean other value ignored' => ['active=all', ['Cable', 'Laptop', 'Mouse', 'Phone', 'Tablet']];
        yield 'exact' => ['name=Phone', ['Phone']];
        yield 'exact list' => ['name[]=Phone&name[]=Cable', ['Cable', 'Phone']];
        yield 'enum by value' => ['status=published', ['Laptop', 'Phone']];
        yield 'enum by case name, multiple' => ['status[]=Draft&status[]=ARCHIVED', ['Cable', 'Mouse', 'Tablet']];
        yield 'to-many relation by id' => ['tags[]=__TAG__', ['Laptop', 'Tablet']];
        yield 'to-many relation none' => ['tags=null', ['Cable', 'Mouse', 'Phone']];
        yield 'combined' => ['status=published&active=false&price[gte]=100', ['Phone']];
        yield 'unknown filter ignored' => ['nope=1', ['Cable', 'Laptop', 'Mouse', 'Phone', 'Tablet']];
    }

    public function testToOneRelationFilterById(): void
    {
        $category = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Category::class)->findOneBy(['name' => 'Computers']);

        $data = $this->api('GET', '/api/products?order[name]=asc&category=' . $category?->getId(), $this->token);
        self::assertSame(['Laptop', 'Tablet'], $this->names($data));

        $data = $this->api('GET', '/api/products?order[name]=asc&category=null', $this->token);
        self::assertSame(['Phone'], $this->names($data));

        $data = $this->api('GET', '/api/products?order[name]=asc&category[]=null&category[]=' . $category?->getId(), $this->token);
        self::assertSame(['Laptop', 'Phone', 'Tablet'], $this->names($data));
    }

    public function testComputedFacetsAreAddedToTheCollection(): void
    {
        $data = $this->api('GET', '/api/products?status=published', $this->token);

        self::assertSame('2024-03-01', $data['filters']['publishedAt']['options']['min']);
        self::assertSame([2024], $data['filters']['publishedAt']['options']['years']);
        // The category facet ignores its own filter, not the others.
        $data = $this->api('GET', '/api/products?status=published&category=null', $this->token);
        self::assertSame(1, $data['filters']['category']['options'][0]['total']);
        self::assertSame(['Phone'], $this->names($data));
        self::assertArrayHasKey('totalItems', $data);
    }

    public function testTheParametersAreDocumented(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi', server: ['HTTP_ACCEPT' => 'application/vnd.openapi+json']);
        $this->assertStatus(200);
        $document = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $names = array_column($document['paths']['/api/products']['get']['parameters'], 'name');

        foreach (['page', 'itemsPerPage', 'order[name]', 'order[category.name]', 'search', 'name', 'price[gte]', 'price[lte]', 'publishedAt[after]', 'publishedAt[before]', 'active', 'status[]', 'category', 'tags[]'] as $name) {
            self::assertContains($name, $names, implode(', ', $names));
        }
    }

    public function testCollectionQueryCountDoesNotDependOnTheRows(): void
    {
        $this->client->disableReboot();
        // The first request of the token records its use.
        $this->queryCount('/api/users');
        $few = $this->queryCount('/api/users');

        foreach (range(1, 5) as $i) {
            $this->fixtures->user('more-' . $i . '@example.com', ['view more']);
        }

        // `#[EagerLoad]` contributor (inverse one-to-one) and the roles of the list group:
        // fetch-joined, not one query per user (users are not cached).
        self::assertSame($few, $this->queryCount('/api/users'));
    }

    public function testItemReadKeepsFetchJoinedRelations(): void
    {
        $products = $this->api('GET', '/api/products?order[name]=asc', $this->token);
        $laptop = $products['member'][1];

        $data = $this->api('GET', '/api/products/' . $laptop['id'], $this->token);

        $this->assertStatus(200);
        self::assertSame('Computers', $data['category']['name']);
    }

    private function queryCount(string $uri): int
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $queries = self::getContainer()->get('doctrine.debug_data_holder');
        $queries->reset();

        $this->api('GET', $uri, $this->token);
        $this->assertStatus(200);

        return \count($queries->getData()['default'] ?? []);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private function names(array $data): array
    {
        return array_column($data['member'], 'name');
    }

    private function createCatalog(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $computers = new Category('Computers');
        $accessories = new Category('Accessories');
        $mobile = new Category('Mobile');
        $tag = new Category('Tag');

        foreach ([$computers, $accessories, $mobile, $tag] as $category) {
            $entityManager->persist($category);
        }

        $rows = [
            ['Laptop', 1500, true, ProductStatus::Published, '2024-03-01', $computers, true],
            ['Tablet', 600, null, ProductStatus::Archived, '2023-06-15', $computers, true],
            ['Phone', 800, false, ProductStatus::Published, '2024-09-10', null, false],
            ['Mouse', 25, true, ProductStatus::Draft, null, $accessories, false],
            ['Cable', 0, false, ProductStatus::Draft, '2025-01-05 00:30', $accessories, false],
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
                $product->addTag($tag);
            }

            $entityManager->persist($product);
        }

        $entityManager->flush();
        $this->tagId = (int) $tag->getId();
    }

    private int $tagId = 0;

    /**
     * @param array<string, mixed>|null $json
     *
     * @return array<string, mixed>
     */
    protected function api(string $method, string $uri, ?string $token = null, ?array $json = null, string $contentType = 'application/json', ?string $locale = null): array
    {
        return parent::api($method, str_replace('__TAG__', (string) $this->tagId, $uri), $token, $json, $contentType, $locale);
    }
}
