<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Cache;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Gingerminds\CoreBundle\Model\CacheableResourceInterface;
use Gingerminds\CoreBundle\Model\CacheCascadeInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Contracts\Service\ResetInterface;

final class CacheInvalidationListener implements ResetInterface
{
    /** @var array<string, true> */
    private array $tags = [];

    public function __construct(
        private readonly TagAwareAdapterInterface $cache,
        private readonly CacheKeyBuilder $keyBuilder,
        private readonly bool $enabled,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!$this->enabled) {
            return;
        }

        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach (
            [
                ...$uow->getScheduledEntityInsertions(),
                ...$uow->getScheduledEntityUpdates(),
                ...$uow->getScheduledEntityDeletions(),
            ] as $entity
        ) {
            $this->collect($entity);
        }

        foreach ($uow->getScheduledCollectionUpdates() as $collection) {
            $this->collect($collection->getOwner());
        }

        foreach ($uow->getScheduledCollectionDeletions() as $collection) {
            $this->collect($collection->getOwner());
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->tags) {
            return;
        }

        $tags = array_keys($this->tags);
        $this->tags = [];

        $this->cache->invalidateTags($tags);
    }

    public function reset(): void
    {
        $this->tags = [];
    }

    private function collect(?object $entity): void
    {
        if ($entity instanceof CacheableResourceInterface) {
            $tag = $entity::getCacheKey();
            $id = method_exists($entity, 'getId') ? $entity->getId() : null;

            $this->tags[$this->keyBuilder->listTag($tag)] = true;

            if (\is_int($id) || \is_string($id)) {
                $this->tags[$this->keyBuilder->itemTag($tag, $id)] = true;
            }
        }

        if ($entity instanceof CacheCascadeInterface) {
            foreach ($entity::getCascadeCacheKeys() as $parentTag) {
                $this->tags[$this->keyBuilder->resourceTag($parentTag)] = true;
            }
        }
    }
}
