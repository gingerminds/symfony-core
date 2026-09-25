<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller;

use Gingerminds\CoreBundle\Model\FilterableInterface;
use Gingerminds\CoreBundle\Model\ResourceInterface;
use Gingerminds\CoreBundle\Model\SearchableInterface;
use Gingerminds\CoreBundle\Model\SortableInterface;
use Gingerminds\CoreBundle\Pagination\Paginator;
use Gingerminds\CoreBundle\Repository\ListQuery;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;
use Gingerminds\CoreBundle\Resource\ResourceDefinition;
use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class AbstractCrudController
{
    use ControllerTrait;

    private ?ResourceDefinition $resource = null;

    public function __construct(
        protected readonly CrudContext $context,
    ) {
    }

    public function index(Request $request): Response
    {
        $resource = $this->getResource();
        $this->denyAccessUnlessGranted(AbstractResourceVoter::VIEW, $resource->name);

        $query = $this->createListQuery($request);
        $items = $this->getRepository()->paginate($query);

        return $this->render($resource->template('index'), [
            ...$this->getCommonParameters(),
            'items' => $items,
            'list_query' => $query,
            'filters' => $query->filters,
            ...$this->getIndexParameters($request, $items),
        ]);
    }

    public function new(Request $request): Response
    {
        $resource = $this->getResource();
        $this->denyAccessUnlessGranted(AbstractResourceVoter::CREATE, $resource->name);

        $entity = $this->createEntity();
        $form = $this->createResourceForm($entity, true);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getRepository()->save($entity, $form);
            $this->addResourceFlash($request, 'flash.created', $this->getLabel($entity));

            return $this->redirectAfterSave($entity, true);
        }

        return $this->render($resource->template('new'), [
            ...$this->getCommonParameters(),
            'entity' => $entity,
            'form' => $form->createView(),
            'is_new' => true,
            ...$this->getFormParameters($entity, $form, true),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    public function edit(Request $request, string $id): Response
    {
        $resource = $this->getResource();
        $entity = $this->findEntity($id);
        $this->denyAccessUnlessGranted(AbstractResourceVoter::EDIT, $entity);

        $form = $this->createResourceForm($entity, false);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getRepository()->save($entity, $form);
            $this->addResourceFlash($request, 'flash.updated', $this->getLabel($entity));

            return $this->redirectAfterSave($entity, false);
        }

        return $this->render($resource->template('edit'), [
            ...$this->getCommonParameters(),
            'entity' => $entity,
            'form' => $form->createView(),
            'is_new' => false,
            ...$this->getFormParameters($entity, $form, false),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    public function delete(Request $request, string $id): Response
    {
        $resource = $this->getResource();
        $entity = $this->findEntity($id);
        $this->denyAccessUnlessGranted(AbstractResourceVoter::DELETE, $entity);

        if (!$this->isCsrfTokenValid('delete-' . $id, $request->getPayload()->getString('_token'))) {
            $this->addFlash($request, 'danger', $this->trans('flash.invalid_csrf'));

            return $this->redirectToRoute($resource->route('index'));
        }

        $error = $this->getDeleteError($entity);

        if (null !== $error) {
            $this->addFlash($request, 'danger', $error);

            return $this->redirectToRoute($resource->route('index'));
        }

        $label = $this->getLabel($entity);
        $this->getRepository()->remove($entity);

        $this->addResourceFlash($request, 'flash.deleted', $label);

        return $this->redirectToRoute($resource->route('index'));
    }

    /**
     * Success flash naming the resource and the entity label (flash.created, flash.updated, flash.deleted).
     */
    private function addResourceFlash(Request $request, string $message, string $label): void
    {
        $resource = $this->getResource();

        $this->addFlash($request, 'success', $this->trans($message, [
            '%resource%' => $this->trans($resource->translationKey('name_s'), [], $resource->translationDomain),
            '%label%' => $label,
        ]));
    }

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

    protected function createListQuery(Request $request): ListQuery
    {
        return ListQuery::fromRequest($request);
    }

    protected function createEntity(): object
    {
        $class = $this->getResource()->entity;

        return new $class();
    }

    protected function findEntity(string $id): object
    {
        return $this->getRepository()->findOneForRead($id)
            ?? throw new NotFoundHttpException(\sprintf('%s "%s" not found.', $this->getResource()->name, $id));
    }

    /**
     * @return FormInterface<mixed>
     */
    protected function createResourceForm(object $entity, bool $isNew): FormInterface
    {
        $formType = $this->getResource()->form
            ?? throw new \LogicException(\sprintf('No form is configured for the resource "%s".', $this->getResource()->name));

        return $this->context->formFactory->create($formType, $entity, $this->getFormOptions($entity, $isNew));
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
        if ($isNew) {
            return $this->redirectToRoute($this->getResource()->route('index'));
        }

        if (!$entity instanceof ResourceInterface) {
            return $this->redirectToRoute($this->getResource()->route('index'));
        }

        return $this->redirectToRoute($this->getResource()->route('edit'), ['id' => $entity->getId()]);
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
            'entity_class' => $resource->entity,
            'is_searchable' => is_subclass_of($resource->entity, SearchableInterface::class),
            'is_filterable' => is_subclass_of($resource->entity, FilterableInterface::class),
            'is_sortable' => is_subclass_of($resource->entity, SortableInterface::class),
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
}
