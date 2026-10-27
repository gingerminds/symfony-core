<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\ValueResolver;

use Gingerminds\CoreBundle\Resource\ResourceDefinition;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The registered resource named by the route parameter of the argument's name, 404 when there is
 * none: resolved before the #[IsGranted] checks, an unknown resource is a 404 rather than a 403.
 *
 *     #[Route('/_autocomplete/{resource}')]
 *     #[IsGranted('VIEW', subject: new Expression('args["resource"].name'))]
 *     public function __invoke(#[ValueResolver(ResourceDefinitionValueResolver::class)] ResourceDefinition $resource)
 *
 * A targeted resolver: it only runs for the arguments that name it.
 */
final readonly class ResourceDefinitionValueResolver implements ValueResolverInterface
{
    public function __construct(
        private ResourceRegistry $resources,
    ) {
    }

    /**
     * @return iterable<ResourceDefinition>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $name = $request->attributes->get($argument->getName());

        if (!\is_string($name) || !$this->resources->has($name)) {
            throw new NotFoundHttpException(\sprintf('Unknown resource "%s".', \is_scalar($name) ? (string) $name : ''));
        }

        return [$this->resources->get($name)];
    }
}
