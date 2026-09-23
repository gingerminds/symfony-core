<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Doctrine\EventListener;

use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Psr\Clock\ClockInterface;

final readonly class TimestampableListener
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof TimestampableInterface) {
            return;
        }

        $now = $this->now();

        if (!$entity->getCreatedAt() instanceof \DateTimeImmutable) {
            $entity->setCreatedAt($now);
        }

        $entity->setUpdatedAt($now);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof TimestampableInterface) {
            $entity->setUpdatedAt($this->now());
        }
    }

    private function now(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->clock->now());
    }
}
