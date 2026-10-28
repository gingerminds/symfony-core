<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;

/**
 * Decorates the API Platform Doctrine persist and remove processors: an entity whose
 * repository is a bundle RepositoryInterface is written by the native processor inside the
 * repository's save / remove hooks (saveWith() / removeWith()), the ones the admin goes
 * through (default role kept unique, contributor released, project beforeSave()...).
 * Any other entity is left to the native processor.
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
final readonly class RepositoryProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        private ProcessorInterface $decorated,
        private ManagerRegistry $registry,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $write = fn (): mixed => $this->decorated->process($data, $operation, $uriVariables, $context);
        $repository = \is_object($data) && $this->registry->getManagerForClass($data::class) instanceof ObjectManager
            ? $this->registry->getRepository($data::class)
            : null;

        if (!$repository instanceof RepositoryInterface) {
            return $write();
        }

        return $operation instanceof DeleteOperationInterface
            ? $repository->removeWith($data, $write)
            : $repository->saveWith($data, $write);
    }
}
