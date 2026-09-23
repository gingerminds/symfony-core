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
 * Pagination, sorting, search and every built-in filter type, through the
 * shared repository behind the API provider.
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

    public function testSortingThroughARelation(): void
    {
        $data = $this->api('GET', '/api/products?sortBy=category.name&sort=desc', $this->token);

        // Computers, Accessories, then no category; ties by id.
        self::assertSame(['Laptop', 'Tablet', 'Mouse', 'Cable', 'Phone'], $this->names($data));
    }

    public function testUnknownSortPropertyIsIgnored(): void
    {
        $this->api('GET', '/api/products?sortBy=unknown); DROP TABLE products; --', $this->token);

        $this->assertStatus(200);
    }

    public function testSearchMatchesOwnAndRelatedFields(): void
    {
        self::assertSame(['Laptop'], $this->names($this->api('GET', '/api/products?filters[search]=LAP&sortBy=name', $this->token)));
        self::assertSame(['Cable', 'Mouse'], $this->names($this->api('GET', '/api/products?filters[search]=accessor&sortBy=name', $this->token)));
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('filters')]
    public function testFilters(string $query, array $expected): void
    {
        $data = $this->api('GET', '/api/products?sortBy=name&' . $query, $this->token);

        $this->assertStatus(200);
        self::assertSame($expected, $this->names($data));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function filters(): iterable
    {
        yield 'number range' => ['filters[price][from]=100&filters[price][to]=900', ['Phone', 'Tablet']];
        yield 'number zero bound' => ['filters[price][to]=0', ['Cable']];
        yield 'date range' => ['filters[publishedAt][from]=2024-01-01&filters[publishedAt][to]=2024-12-31', ['Laptop', 'Phone']];
        yield 'boolean yes' => ['filters[active]=yes', ['Laptop', 'Mouse']];
        yield 'boolean no includes null' => ['filters[active]=no', ['Cable', 'Phone', 'Tablet']];
        yield 'boolean all' => ['filters[active]=all', ['Cable', 'Laptop', 'Mouse', 'Phone', 'Tablet']];
        yield 'enum by value' => ['filters[status]=published', ['Laptop', 'Phone']];
        yield 'enum by case name, multiple' => ['filters[status][]=Draft&filters[status][]=ARCHIVED', ['Cable', 'Mouse', 'Tablet']];
        yield 'to-many relation' => ['filters[tags][]=__TAG__', ['Laptop', 'Tablet']];
        yield 'unknown filter ignored' => ['filters[nope]=1', ['Cable', 'Laptop', 'Mouse', 'Phone', 'Tablet']];
    }

    public function testToOneRelationFilter(): void
    {
        $category = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Category::class)->findOneBy(['name' => 'Computers']);

        $data = $this->api('GET', '/api/products?sortBy=name&filters[category]=' . $category?->getId(), $this->token);
        self::assertSame(['Laptop', 'Tablet'], $this->names($data));

        $data = $this->api('GET', '/api/products?sortBy=name&filters[category]=null', $this->token);
        self::assertSame(['Phone'], $this->names($data));
    }

    public function testComputedFacetsAreInjected(): void
    {
        $data = $this->api('GET', '/api/products?filters[status]=published', $this->token);

        self::assertSame('2024-03-01', $data['filters']['publishedAt']['options']['min']);
        self::assertSame([2024], $data['filters']['publishedAt']['options']['years']);
        self::assertSame(1, $data['filters']['category']['options'][0]['total']);
    }

    public function testItemReadKeepsFetchJoinedRelations(): void
    {
        $products = $this->api('GET', '/api/products?sortBy=name', $this->token);
        $laptop = $products['member'][1];

        $data = $this->api('GET', '/api/products/' . $laptop['id'], $this->token);

        $this->assertStatus(200);
        self::assertSame('Computers', $data['category']['name']);
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
