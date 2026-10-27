<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\ValueResolver;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The entity of the current resource route (`_resource` default, `{id}` parameter), loaded with
 * the resource repository's findOneForRead(): what #[MapEntity] does, for an entity class only
 * known at runtime (`resources.*.entity` can be overridden by the project).
 *
 *     public function edit(Request $request, #[ValueResolver(ResourceEntityValueResolver::class)] object $entity)
 *
 * A targeted resolver: it only runs for the arguments that name it.
 */
final readonly class ResourceEntityValueResolver implements ValueResolverInterface
{
    public function __construct(
        private ResourceRegistry $resources,
        private ManagerRegistry $doctrine,
    ) {
    }

    /**
     * @return iterable<object>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $name = $request->attributes->get('_resource');
        $id = $request->attributes->get('id');

        if (!\is_string($name) || !\is_scalar($id)) {
            throw new \LogicException(\sprintf('The argument "$%s" needs a resource route: a "_resource" default and an "{id}" parameter.', $argument->getName()));
        }

        $entityClass = $this->resources->getEntityClass($name);
        $repository = $this->doctrine->getRepository($entityClass);

        if (!$repository instanceof RepositoryInterface) {
            throw new \LogicException(\sprintf('The repository of "%s" must implement "%s" (extend AbstractRepository).', $entityClass, RepositoryInterface::class));
        }

        return [
            $repository->findOneForRead((string) $id)
                ?? throw new NotFoundHttpException(\sprintf('%s "%s" not found.', $name, (string) $id)),
        ];
    }
}
