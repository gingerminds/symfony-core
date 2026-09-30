<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Api;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Tests\Application\Entity\Product;
use Gingerminds\CoreBundle\Tests\Functional\ApiTestCase;

/**
 * API rate limit (gingerminds_core.api.rate_limit: 3 requests in the test application)
 * and its per operation / per route overrides.
 */
final class RateLimitTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Counters live in an in-memory storage of the kernel: keep the same one.
        $this->client->disableReboot();
    }

    public function testApiRequestsAreLimitedPerClient(): void
    {
        foreach ([2, 1, 0] as $remaining) {
            $this->api('GET', '/api/products');
            $this->assertStatus(200);
            self::assertResponseHeaderSame('X-RateLimit-Limit', '3');
            self::assertResponseHeaderSame('X-RateLimit-Remaining', (string) $remaining);
        }

        $data = $this->api('GET', '/api/products', locale: 'fr');

        $this->assertStatus(429);
        self::assertSame('Trop de requêtes. Réessayez dans quelques instants.', $data['message']);
        self::assertResponseHasHeader('Retry-After');

        // An authenticated user has its own counter.
        $token = $this->fixtures->token($this->fixtures->user('limited@example.com', superAdmin: true));
        $this->api('GET', '/api/products', $token);
        $this->assertStatus(200);
        self::assertResponseHeaderSame('X-RateLimit-Remaining', '2');
    }

    public function testOperationUsesItsOwnLimiter(): void
    {
        $product = new Product();
        $product->setName('Limited');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($product);
        $entityManager->flush();

        $this->api('GET', '/api/products/' . $product->getId());
        $this->assertStatus(200);
        self::assertResponseHeaderSame('X-RateLimit-Limit', '1');

        $this->api('GET', '/api/products/' . $product->getId());
        $this->assertStatus(429);

        // The default API limiter is untouched.
        $this->api('GET', '/api/products');
        $this->assertStatus(200);
        self::assertResponseHeaderSame('X-RateLimit-Remaining', '2');
    }

    public function testRouteDefaultOverridesTheLimiter(): void
    {
        $this->client->request('GET', '/rate-limited');
        self::assertResponseRedirects('/');
        $this->client->request('GET', '/rate-limited');
        self::assertResponseStatusCodeSame(429);

        foreach (range(1, 4) as $ignored) {
            $this->client->request('GET', '/api/unlimited');
            self::assertResponseRedirects('/');
            self::assertResponseNotHasHeader('X-RateLimit-Limit');
        }
    }
}
