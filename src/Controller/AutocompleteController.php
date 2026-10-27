<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Model\ResourceInterface;
use Gingerminds\CoreBundle\Repository\ListQuery;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Remote choices of the resource autocomplete fields (`ResourceAutocompleteType`).
 */
final class AutocompleteController extends AbstractController
{
    private const int PER_PAGE = 20;

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly ManagerRegistry $doctrine,
    ) {
    }

    #[IsGranted(ResourceVoter::VIEW, subject: 'resource')]
    public function __invoke(Request $request, string $resource): JsonResponse
    {
        if (!$this->resources->has($resource)) {
            throw $this->createNotFoundException(\sprintf('Unknown resource "%s".', $resource));
        }

        $repository = $this->doctrine->getRepository($this->resources->getEntityClass($resource));

        if (!$repository instanceof RepositoryInterface) {
            throw $this->createNotFoundException(\sprintf('The resource "%s" is not searchable.', $resource));
        }

        $search = trim((string) $request->query->get('query', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $items = $repository->paginate(new ListQuery(
            page: $page,
            itemsPerPage: self::PER_PAGE,
            filters: '' !== $search ? [ListQuery::SEARCH_FILTER => $search] : [],
        ));

        $results = [];

        foreach ($items as $item) {
            $id = $item instanceof ResourceInterface ? (string) $item->getId() : '';
            $results[] = [
                'value' => $id,
                'text' => $item instanceof \Stringable ? (string) $item : '#' . $id,
            ];
        }

        return $this->json([
            'results' => $results,
            'next_page' => $items->hasNextPage()
                ? $this->generateUrl('gingerminds_core_autocomplete', ['resource' => $resource, 'query' => $search, 'page' => $page + 1])
                : null,
        ]);
    }
}
