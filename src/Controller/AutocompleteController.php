<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller;

use Gingerminds\CoreBundle\Model\ResourceInterface;
use Gingerminds\CoreBundle\Repository\ListQuery;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;
use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class AutocompleteController
{
    private const int PER_PAGE = 20;

    public function __construct(
        private CrudContext $context,
    ) {
    }

    public function __invoke(Request $request, string $resource): JsonResponse
    {
        if (!$this->context->resources->has($resource)) {
            throw new NotFoundHttpException(\sprintf('Unknown resource "%s".', $resource));
        }

        if (!$this->context->authorizationChecker->isGranted(AbstractResourceVoter::VIEW, $resource)) {
            throw new AccessDeniedException();
        }

        $repository = $this->context->doctrine->getRepository($this->context->resources->getEntityClass($resource));

        if (!$repository instanceof RepositoryInterface) {
            throw new NotFoundHttpException(\sprintf('The resource "%s" is not searchable.', $resource));
        }

        $search = trim((string) $request->query->get('query', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $query = new ListQuery(page: $page, itemsPerPage: self::PER_PAGE, filters: '' !== $search ? [ListQuery::SEARCH_FILTER => $search] : []);
        $items = $repository->paginate($query);

        $results = [];

        foreach ($items as $item) {
            $id = $item instanceof ResourceInterface ? (string) $item->getId() : '';
            $results[] = [
                'value' => $id,
                'text' => $item instanceof \Stringable ? (string) $item : '#' . $id,
            ];
        }

        return new JsonResponse([
            'results' => $results,
            'next_page' => $items->hasNextPage()
                ? $this->context->urlGenerator->generate('gingerminds_core_autocomplete', [
                    'resource' => $resource,
                    'query' => $search,
                    'page' => $page + 1,
                ])
                : null,
        ]);
    }
}
