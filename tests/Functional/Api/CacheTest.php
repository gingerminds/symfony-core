<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Api;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Tests\Application\Entity\Product;
use Gingerminds\CoreBundle\Tests\Functional\ApiTestCase;

/**
 * API response cache of CacheableResourceInterface resources.
 */
final class CacheTest extends ApiTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        self::getContainer()->get('gingerminds_core.resource_cache')->clear();
        $this->token = $this->fixtures->token($this->fixtures->user('root@example.com', superAdmin: true));
    }

    public function testCollectionIsServedFromCacheUntilAWrite(): void
    {
        $product = $this->createProduct('Cached');

        $this->api('GET', '/api/products', $this->token);
        self::assertSame('MISS', $this->client->getResponse()->headers->get('X-Gingerminds-Cache'));

        $data = $this->api('GET', '/api/products', $this->token);
        self::assertSame('HIT', $this->client->getResponse()->headers->get('X-Gingerminds-Cache'));
        self::assertSame('Cached', $data['member'][0]['name']);

        // The browser reboots the kernel between requests: use the current entity manager.
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->find(Product::class, $product->getId())?->setName('Renamed');
        $entityManager->flush();

        $data = $this->api('GET', '/api/products', $this->token);
        self::assertSame('MISS', $this->client->getResponse()->headers->get('X-Gingerminds-Cache'));
        self::assertSame('Renamed', $data['member'][0]['name']);
    }

    public function testCacheKeyDependsOnQueryString(): void
    {
        $this->createProduct('First');

        $this->api('GET', '/api/products?page=1', $this->token);
        $this->api('GET', '/api/products?page=1&itemsPerPage=5', $this->token);

        self::assertSame('MISS', $this->client->getResponse()->headers->get('X-Gingerminds-Cache'));
    }

    public function testItemIsInvalidatedOnRemoval(): void
    {
        $product = $this->createProduct('Removed');
        $id = $product->getId();

        $this->api('GET', '/api/products/' . $id, $this->token);
        $this->api('GET', '/api/products/' . $id, $this->token);
        self::assertSame('HIT', $this->client->getResponse()->headers->get('X-Gingerminds-Cache'));

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->remove($entityManager->find(Product::class, $id));
        $entityManager->flush();

        $this->api('GET', '/api/products/' . $id, $this->token);
        $this->assertStatus(404);
    }

    public function testNonCacheableResourcesAreNotCached(): void
    {
        $this->api('GET', '/api/roles', $this->token);
        $this->api('GET', '/api/roles', $this->token);

        self::assertNull($this->client->getResponse()->headers->get('X-Gingerminds-Cache'));
    }

    private function createProduct(string $name): Product
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $product = new Product()->setName($name);
        $entityManager->persist($product);
        $entityManager->flush();

        return $product;
    }
}
