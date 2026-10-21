<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller;

use Gingerminds\CoreBundle\Repository\RepositoryInterface;
use Gingerminds\CoreBundle\Resource\ResourceDefinition;

/**
 * Resolves the resource bound to a controller and its repository.
 *
 * @property CrudContext $context
 */
trait ResourceControllerTrait
{
    private ?ResourceDefinition $resource = null;

    protected function getResourceName(): string
    {
        foreach ($this->context->resources->all() as $definition) {
            if (null !== $definition->controller && is_a($this, $definition->controller)) {
                return $definition->name;
            }
        }

        throw new \LogicException(
            \sprintf('No resource is bound to the controller "%s". Register it with #[AsCrudController] or in "gingerminds_core.resources".', static::class),
        );
    }

    protected function getResource(): ResourceDefinition
    {
        return $this->resource ??= $this->context->resources->get($this->getResourceName());
    }

    /**
     * @return RepositoryInterface<object>
     */
    protected function getRepository(): RepositoryInterface
    {
        $repository = $this->context->doctrine->getRepository($this->getResource()->entity);

        if (!$repository instanceof RepositoryInterface) {
            throw new \LogicException(
                \sprintf(
                    'The repository of "%s" must implement "%s" (extend AbstractRepository).',
                    $this->getResource()->entity,
                    RepositoryInterface::class,
                ),
            );
        }

        return $repository;
    }
}
