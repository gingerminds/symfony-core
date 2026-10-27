<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
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
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Admin CRUD of a resource. The resource is the `_resource` default of the current route;
 * the entity of edit/delete is resolved by ResourceEntityValueResolver (404 before the access check).
 */
abstract class AbstractCrudController extends AbstractController
{
    /**
     * The resource name of the current route, subject of the list/create access checks.
     */
    protected const string RESOURCE_SUBJECT = 'request.attributes.get("_resource")';

    public static function getSubscribedServices(): array
    {
        return [
            ...parent::getSubscribedServices(),
            ResourceRegistry::class => ResourceRegistry::class,
            'doctrine' => ManagerRegistry::class,
            'translator' => TranslatorInterface::class,
        ];
    }

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

    protected function getDeleteError(object $entity): ?string
    {
        return null;
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
