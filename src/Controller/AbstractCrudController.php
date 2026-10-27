<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Gingerminds\CoreBundle\Controller\ValueResolver\ResourceEntityValueResolver;
use Gingerminds\CoreBundle\Model\FilterableInterface;
use Gingerminds\CoreBundle\Model\ResourceInterface;
use Gingerminds\CoreBundle\Model\SearchableFields;
use Gingerminds\CoreBundle\Model\SortableFields;
use Gingerminds\CoreBundle\Pagination\Paginator;
use Gingerminds\CoreBundle\Repository\ListQuery;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;
use Gingerminds\CoreBundle\Resource\RedirectTarget;
use Gingerminds\CoreBundle\Resource\ResourceDefinition;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Admin CRUD of a resource.
 *
 * The #[Route] of the actions are relative to the resource: CrudRouteLoader loads them for each
 * resource bound to the controller, under `/{admin_prefix}/{path}`, named `{route_prefix}_{name}`,
 * with the `_resource` default naming the resource. A subclass adds its own actions the same way
 * (`#[Route('/reorder', name: 'reorder')]` serves `/{admin_prefix}/{path}/reorder`, route
 * `{route_prefix}_reorder`). The entity of edit/delete is resolved by ResourceEntityValueResolver
 * and must belong to the route (entityBelongsToRoute(), the parent of a nested resource): 404
 * before the access check.
 */
abstract class AbstractCrudController extends AbstractController
{
    /**
     * The resource name of the current route, subject of the list/create access checks.
     */
    protected const string RESOURCE_SUBJECT = 'request.attributes.get("_resource")';

    protected const string ID_REQUIREMENT = '[^/]+';

    public static function getSubscribedServices(): array
    {
        return [
            ...parent::getSubscribedServices(),
            ResourceRegistry::class => ResourceRegistry::class,
            'doctrine' => ManagerRegistry::class,
            'translator' => TranslatorInterface::class,
            'validator' => ValidatorInterface::class,
        ];
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted(ResourceVoter::VIEW, subject: new Expression(self::RESOURCE_SUBJECT))]
    public function index(Request $request): Response
    {
        $query = $this->createListQuery($request);
        $items = $this->getRepository()->paginate($query);

        return $this->render($this->getResource()->template('index'), [
            ...$this->getCommonParameters(),
            'items' => $items,
            'list_query' => $query,
            'filters' => $query->filters,
            ...$this->getIndexParameters($request, $items),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted(ResourceVoter::CREATE, subject: new Expression(self::RESOURCE_SUBJECT))]
    public function new(Request $request): Response
    {
        $entity = $this->createEntity();
        $form = $this->createResourceForm($entity, true);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getRepository()->save($entity);
            $this->addResourceFlash('flash.created', $this->getLabel($entity));

            return $this->redirectAfterSave($entity, true);
        }

        // AbstractController::render() answers 422 for a submitted invalid form.
        return $this->render($this->getResource()->template('new'), [
            ...$this->getCommonParameters(),
            'entity' => $entity,
            'form' => $form,
            'is_new' => true,
            ...$this->getFormParameters($entity, $form, true),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => self::ID_REQUIREMENT], methods: ['GET', 'POST'])]
    #[IsGranted(ResourceVoter::EDIT, subject: 'entity')]
    public function edit(Request $request, #[ValueResolver(ResourceEntityValueResolver::class)] object $entity): Response
    {
        $form = $this->createResourceForm($entity, false);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getRepository()->save($entity);
            $this->addResourceFlash('flash.updated', $this->getLabel($entity));

            return $this->redirectAfterSave($entity, false);
        }

        return $this->render($this->getResource()->template('edit'), [
            ...$this->getCommonParameters(),
            'entity' => $entity,
            'form' => $form,
            'is_new' => false,
            ...$this->getFormParameters($entity, $form, false),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => self::ID_REQUIREMENT], methods: ['POST'])]
    #[IsGranted(ResourceVoter::DELETE, subject: 'entity')]
    #[IsCsrfTokenValid(new Expression('"delete-" ~ request.attributes.get("id")'))]
    public function delete(#[ValueResolver(ResourceEntityValueResolver::class)] object $entity): Response
    {
        $error = $this->getDeleteError($entity);

        if (null !== $error) {
            $this->addFlash('danger', $error);
        } else {
            $label = $this->getLabel($entity);
            $this->getRepository()->remove($entity);
            $this->addResourceFlash('flash.deleted', $label);
        }

        return $this->redirectToRoute($this->getResource()->route('index'), $this->getRouteParameters(), Response::HTTP_SEE_OTHER);
    }

    /**
     * The resource of the current route (its `_resource` default).
     */
    protected function getResource(): ResourceDefinition
    {
        $name = $this->getRequestStack()->getCurrentRequest()?->attributes->get('_resource');

        if (!\is_string($name)) {
            throw new \LogicException(\sprintf('The current route has no "_resource" default: "%s" only serves resource routes.', static::class));
        }

        return $this->getResourceRegistry()->get($name);
    }

    /**
     * @return RepositoryInterface<object>
     */
    protected function getRepository(): RepositoryInterface
    {
        $entityClass = $this->getResource()->entity;
        $repository = $this->getDoctrine()->getRepository($entityClass);

        if (!$repository instanceof RepositoryInterface) {
            throw new \LogicException(\sprintf('The repository of "%s" must implement "%s" (extend AbstractRepository).', $entityClass, RepositoryInterface::class));
        }

        return $repository;
    }

    protected function getResourceRegistry(): ResourceRegistry
    {
        $resources = $this->container->get(ResourceRegistry::class);
        \assert($resources instanceof ResourceRegistry);

        return $resources;
    }

    protected function getDoctrine(): ManagerRegistry
    {
        $doctrine = $this->container->get('doctrine');
        \assert($doctrine instanceof ManagerRegistry);

        return $doctrine;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function trans(string $key, array $parameters = [], string $domain = 'GingermindsCore'): string
    {
        $translator = $this->container->get('translator');
        \assert($translator instanceof TranslatorInterface);

        return $translator->trans($key, $parameters, $domain);
    }

    protected function createListQuery(Request $request): ListQuery
    {
        return ListQuery::fromRequest($request);
    }

    protected function createEntity(): object
    {
        $class = $this->getResource()->entity;

        return new $class();
    }

    /**
     * @return FormInterface<mixed>
     */
    protected function createResourceForm(object $entity, bool $isNew): FormInterface
    {
        $formType = $this->getResource()->form
            ?? throw new \LogicException(\sprintf('No form is configured for the resource "%s".', $this->getResource()->name));

        return $this->createForm($formType, $entity, $this->getFormOptions($entity, $isNew));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getFormOptions(object $entity, bool $isNew): array
    {
        return [];
    }

    protected function redirectAfterSave(object $entity, bool $isNew): Response
    {
        $resource = $this->getResource();

        if (RedirectTarget::Edit === $resource->redirectAfterSave($isNew) && $entity instanceof ResourceInterface) {
            return $this->redirectToRoute($resource->route('edit'), [...$this->getRouteParameters(), 'id' => $entity->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute($resource->route('index'), $this->getRouteParameters(), Response::HTTP_SEE_OTHER);
    }

    /**
     * Parameters of the resource routes besides `id`, e.g. the parent id of a nested resource
     * (`path: 'menus/{menuId}/items'`): those of the current route by default.
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(): array
    {
        $parameters = $this->getRequestStack()->getCurrentRequest()?->attributes->get('_route_params', []);
        $parameters = \is_array($parameters) ? $parameters : [];
        unset($parameters['id'], $parameters['_resource']);

        return $parameters;
    }

    /**
     * Whether the entity of the route's `{id}` (resolved by ResourceEntityValueResolver) belongs to
     * the route's other parameters: false is a 404, checked by ResourceEntityRouteListener before
     * the access and CSRF checks, for every action argument of that resolver (edit, delete, a
     * subclass' own action).
     *
     * By default, each `{xxxId}` parameter naming a to-one association `xxx` of the entity must
     * be the identifier of that association (`categories/{categoryId}/products`: the product's
     * `category`); the other parameters are not checked. Override it for another rule (an
     * association named otherwise, a parent restricted to the current site...), keeping the
     * convention with `parent::entityBelongsToRoute()` if it applies.
     *
     * It is public for the listener: it is not an action (no #[Route]).
     *
     * @param array<string, mixed> $routeParameters the parameters of the route but `id` (getRouteParameters())
     */
    public function entityBelongsToRoute(object $entity, array $routeParameters): bool
    {
        $manager = $this->getDoctrine()->getManagerForClass($entity::class);
        $metadata = $manager?->getClassMetadata($entity::class);

        if (!$manager instanceof ObjectManager || !$metadata instanceof ClassMetadata) {
            return true;
        }

        foreach ($routeParameters as $name => $value) {
            $association = str_ends_with($name, 'Id') ? substr($name, 0, -2) : '';

            if ('' === $association || !\is_scalar($value) || !$metadata->isSingleValuedAssociation($association)) {
                continue;
            }

            $parent = $metadata->getFieldValue($entity, $association);

            if (!\is_object($parent)) {
                return false;
            }

            $identifier = array_values($manager->getClassMetadata($parent::class)->getIdentifierValues($parent));

            if (1 !== \count($identifier) || !\is_scalar($identifier[0]) || (string) $identifier[0] !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Why the entity cannot be deleted, null when it can: by default the first violation of
     * its `{resource}:delete` validation group, the group its API Delete operation validates
     * too (`validate: true`), e.g. the Super-Admin role.
     */
    protected function getDeleteError(object $entity): ?string
    {
        $validator = $this->container->get('validator');
        \assert($validator instanceof ValidatorInterface);

        $violations = $validator->validate($entity, null, [$this->getResource()->name . ':delete']);

        return 0 === \count($violations) ? null : (string) $violations->get(0)->getMessage();
    }

    protected function getLabel(object $entity): string
    {
        return $entity instanceof \Stringable ? (string) $entity : '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getCommonParameters(): array
    {
        $resource = $this->getResource();

        return [
            'resource' => $resource,
            'route_parameters' => $this->getRouteParameters(),
            'entity_class' => $resource->entity,
            'is_searchable' => [] !== SearchableFields::of($resource->entity),
            'is_filterable' => is_subclass_of($resource->entity, FilterableInterface::class),
            'sortable_fields' => SortableFields::of($resource->entity),
            'filter_configs' => is_subclass_of($resource->entity, FilterableInterface::class) ? $resource->entity::getFilters() : [],
        ];
    }

    /**
     * @param Paginator<object> $items
     *
     * @return array<string, mixed>
     */
    protected function getIndexParameters(Request $request, Paginator $items): array
    {
        return [];
    }

    /**
     * Extra new/edit template variables.
     *
     * @param FormInterface<mixed> $form
     *
     * @return array<string, mixed>
     */
    protected function getFormParameters(object $entity, FormInterface $form, bool $isNew): array
    {
        return [];
    }

    /**
     * Success flash naming the resource and the entity label (flash.created, flash.updated, flash.deleted).
     */
    private function addResourceFlash(string $message, string $label): void
    {
        $resource = $this->getResource();

        $this->addFlash('success', $this->trans($message, [
            '%resource%' => $this->trans($resource->translationKey('name_s'), [], $resource->translationDomain),
            '%label%' => $label,
        ]));
    }

    private function getRequestStack(): RequestStack
    {
        $requestStack = $this->container->get('request_stack');
        \assert($requestStack instanceof RequestStack);

        return $requestStack;
    }
}
