<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\EventListener;

use Gingerminds\CoreBundle\Controller\AbstractCrudController;
use Gingerminds\CoreBundle\Controller\ValueResolver\ResourceEntityValueResolver;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The entities resolved by ResourceEntityValueResolver for an AbstractCrudController action must
 * belong to the rest of the route, e.g. the parent of a nested resource
 * (`categories/{categoryId}/products/{id}/edit`): AbstractCrudController::entityBelongsToRoute()
 * returning false is a 404.
 *
 * A value resolver does not know the controller instance, hence this `kernel.controller_arguments`
 * listener. Its priority runs it before the #[IsGranted] and #[IsCsrfTokenValid] checks (dispatched
 * by ControllerAttributesListener at -10000, or at 20 and 25 by their own listeners), so that an
 * entity of another parent is a 404 before any 403, as an unknown id.
 */
final class ResourceEntityRouteListener
{
    public const int PRIORITY = 30;

    public function __invoke(ControllerArgumentsEvent $event): void
    {
        $controller = $event->getController();

        if (!\is_array($controller) || !$controller[0] instanceof AbstractCrudController) {
            return;
        }

        $request = $event->getRequest();
        $routeParameters = $request->attributes->get('_route_params', []);
        $routeParameters = \is_array($routeParameters) ? $routeParameters : [];
        unset($routeParameters['id'], $routeParameters['_resource']);
        $arguments = $event->getNamedArguments();

        foreach (new \ReflectionMethod($controller[0], $controller[1])->getParameters() as $parameter) {
            $entity = $arguments[$parameter->getName()] ?? null;

            if (!\is_object($entity) || !self::isResolvedByResourceEntityValueResolver($parameter)) {
                continue;
            }

            if (!$controller[0]->entityBelongsToRoute($entity, $routeParameters)) {
                throw new NotFoundHttpException(\sprintf(
                    '%s "%s" not found.',
                    (string) $request->attributes->get('_resource'),
                    (string) $request->attributes->get('id'),
                ));
            }
        }
    }

    private static function isResolvedByResourceEntityValueResolver(\ReflectionParameter $parameter): bool
    {
        foreach ($parameter->getAttributes(ValueResolver::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            if (ResourceEntityValueResolver::class === $attribute->newInstance()->resolver) {
                return true;
            }
        }

        return false;
    }
}
