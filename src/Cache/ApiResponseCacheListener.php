<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Cache;

use Symfony\Component\Security\Core\User\UserInterface;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use Gingerminds\CoreBundle\Model\Cacheable;
use Gingerminds\CoreBundle\Model\CacheMetadata;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Caches the API responses of the `#[Cacheable]` resources for the anonymous requests only: the
 * key holds no user, an authenticated request (or one carrying an `Authorization` header) always
 * reaches the application, whatever its response depends on.
 */
final readonly class ApiResponseCacheListener
{
    private const string KEY_ATTRIBUTE = '_gingerminds_cache_key';
    private const string TAGS_ATTRIBUTE = '_gingerminds_cache_tags';
    private const string TTL_ATTRIBUTE = '_gingerminds_cache_ttl';

    public function __construct(
        private TagAwareAdapterInterface $cache,
        private CacheKeyBuilder $keyBuilder,
        private ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private ?ResourceAccessCheckerInterface $resourceAccessChecker,
        private ?TokenStorageInterface $tokenStorage,
        private bool $enabled,
        private int $defaultTtl,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $cacheable = $this->resolveCacheableOperation($event);

        if (null === $cacheable) {
            return;
        }

        [$attribute, $operation] = $cacheable;
        $request = $event->getRequest();
        $resourceTag = $attribute->key;
        $key = $this->keyBuilder->responseKey($resourceTag, $request);
        $item = $this->cache->getItem($key);
        $cached = $item->isHit() ? $item->get() : null;

        if (\is_array($cached) && \is_string($cached['content'] ?? null)) {
            $response = new Response($cached['content'], Response::HTTP_OK, $cached['headers'] ?? []);
            $response->headers->set('X-Gingerminds-Cache', 'HIT');
            $event->setResponse($response);

            return;
        }

        $id = $request->attributes->get('id');
        $tags = [$this->keyBuilder->resourceTag($resourceTag)];
        $tags[] = $operation instanceof CollectionOperationInterface || !\is_scalar($id)
            ? $this->keyBuilder->listTag($resourceTag)
            : $this->keyBuilder->itemTag($resourceTag, (string) $id);

        $request->attributes->set(self::KEY_ATTRIBUTE, $key);
        $request->attributes->set(self::TAGS_ATTRIBUTE, $tags);
        $request->attributes->set(self::TTL_ATTRIBUTE, $attribute->ttl ?? $this->defaultTtl);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $key = $request->attributes->get(self::KEY_ATTRIBUTE);
        $response = $event->getResponse();

        if (!\is_string($key) || Response::HTTP_OK !== $response->getStatusCode() || false === $response->getContent()) {
            return;
        }

        /** @var list<string> $tags */
        $tags = $request->attributes->get(self::TAGS_ATTRIBUTE, []);
        $ttl = (int) $request->attributes->get(self::TTL_ATTRIBUTE, $this->defaultTtl);
        $payload = [
            'content' => $response->getContent(),
            'headers' => array_intersect_key($response->headers->all(), array_flip(['content-type', 'content-language', 'vary', 'link'])),
        ];

        $item = $this->cache->getItem($key)
            ->set($payload)
            ->expiresAfter($ttl > 0 ? $ttl : null)
            ->tag($tags);

        $this->cache->save($item);

        $response->headers->set('X-Gingerminds-Cache', 'MISS');
    }

    /**
     * The `#[Cacheable]` of the resource and the operation of a cacheable API request, null when the
     * response must not be cached.
     *
     * @return array{Cacheable, HttpOperation}|null
     */
    private function resolveCacheableOperation(RequestEvent $event): ?array
    {
        if (!$this->isCacheableRequest($event)) {
            return null;
        }

        $request = $event->getRequest();
        $resourceClass = $request->attributes->get('_api_resource_class');
        $operationName = $request->attributes->get('_api_operation_name');

        if (!\is_string($resourceClass) || !\is_string($operationName) || !class_exists($resourceClass)) {
            return null;
        }

        $attribute = CacheMetadata::cacheable($resourceClass);

        if (!$attribute instanceof Cacheable) {
            return null;
        }

        $operation = $this->resourceMetadataFactory->create($resourceClass)->getOperation($operationName);

        return $operation instanceof HttpOperation && $this->isCacheableOperation($operation, $resourceClass)
            ? [$attribute, $operation]
            : null;
    }

    private function isCacheableRequest(RequestEvent $event): bool
    {
        $request = $event->getRequest();

        return $this->enabled
            && $event->isMainRequest()
            && $request->isMethodCacheable()
            && !$request->headers->has('Authorization')
            && !$this->tokenStorage?->getToken()?->getUser() instanceof UserInterface;
    }

    /**
     * @param class-string $resourceClass
     */
    private function isCacheableOperation(HttpOperation $operation, string $resourceClass): bool
    {
        if (Request::METHOD_GET !== $operation->getMethod()) {
            return false;
        }

        $security = $operation->getSecurity();

        if (null === $security) {
            return true;
        }

        return $operation instanceof CollectionOperationInterface
            && $this->resourceAccessChecker instanceof ResourceAccessCheckerInterface
            && $this->resourceAccessChecker->isGranted($resourceClass, $security, ['object' => null]);
    }
}
