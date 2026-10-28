<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Model\TreeNodes;
use Gingerminds\CoreBundle\Repository\TreeRepositoryInterface;
use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin CRUD of a resource shown as a drag & drop tree (`crud/list_tree.html.twig`), its
 * repository implementing TreeRepositoryInterface (TreeRepositoryTrait):
 *
 * - the index renders the whole tree: `roots`, `children` (by parent id, TreeNodes), and the
 *   `tree_reorder_url` / `tree_csrf_token_id` of the template;
 * - `new?parent_id=` preselects the parent of the new node;
 * - `reorder` (POST JSON `{"ids": [...], "parent_id": ...}`, `X-CSRF-Token` header) applies the
 *   order of one level.
 *
 * getTreeScope() restricts the tree (the items of the menu of the route).
 */
abstract class AbstractTreeCrudController extends AbstractCrudController
{
    public const string PARENT_PARAMETER = 'parent_id';

    protected const string REORDER_TOKEN_ID = '"reorder-" ~ request.attributes.get("_resource")';

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted(ResourceVoter::VIEW, subject: new Expression(self::RESOURCE_SUBJECT))]
    public function index(Request $request): Response
    {
        $resource = $this->getResource();
        $children = TreeNodes::groupByParent($this->getTreeRepository()->findTreeNodes($this->getTreeScope()));

        return $this->render($resource->template('index'), [
            ...$this->getCommonParameters(),
            // The whole tree: no list filters.
            'is_searchable' => false,
            'is_filterable' => false,
            'roots' => $children[TreeNodes::ROOT] ?? [],
            'children' => $children,
            'tree_reorder_url' => $this->isGranted(ResourceVoter::EDIT, $resource->name)
                ? $this->generateUrl($resource->route('reorder'), $this->getRouteParameters())
                : null,
            'tree_csrf_token_id' => 'reorder-' . $resource->name,
        ]);
    }

    #[Route('/reorder', name: 'reorder', methods: ['POST'])]
    #[IsGranted(ResourceVoter::EDIT, subject: new Expression(self::RESOURCE_SUBJECT))]
    #[IsCsrfTokenValid(new Expression(self::REORDER_TOKEN_ID), tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function reorder(Request $request): JsonResponse
    {
        $payload = $request->getPayload();
        $ids = array_values(array_filter($payload->all('ids'), $this->isId(...)));
        $parentId = $payload->get(self::PARENT_PARAMETER);

        $this->getTreeRepository()->reorderLevel($this->isId($parentId) ? $parentId : null, $ids, $this->getTreeScope());

        return new JsonResponse(['success' => true]);
    }

    /**
     * The new node under the `?parent_id=` node, when it is readable.
     */
    protected function createEntity(): object
    {
        $entity = parent::createEntity();
        $requestStack = $this->container->get('request_stack');
        \assert($requestStack instanceof RequestStack);
        $parentId = $requestStack->getCurrentRequest()?->query->get(self::PARENT_PARAMETER);

        if ($this->isId($parentId) && null !== ($parent = $this->getRepository()->findOneForRead($parentId))) {
            $entityManager = $this->getDoctrine()->getManagerForClass($entity::class);
            \assert($entityManager instanceof EntityManagerInterface);
            $entityManager->getClassMetadata($entity::class)->setFieldValue($entity, 'parent', $parent);
        }

        return $entity;
    }

    /**
     * The criteria of the tree of the current route (TreeRepositoryInterface), none by default.
     *
     * @return array<string, mixed>
     */
    protected function getTreeScope(): array
    {
        return [];
    }

    protected function getTreeRepository(): TreeRepositoryInterface
    {
        $repository = $this->getRepository();

        if (!$repository instanceof TreeRepositoryInterface) {
            throw new \LogicException(\sprintf('The repository of "%s" must implement "%s" (TreeRepositoryTrait).', $this->getResource()->entity, TreeRepositoryInterface::class));
        }

        return $repository;
    }

    /**
     * @phpstan-assert-if-true int|string $value
     */
    private function isId(mixed $value): bool
    {
        return \is_int($value) || (\is_string($value) && '' !== $value);
    }
}
