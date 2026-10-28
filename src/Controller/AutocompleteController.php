<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\ApiPlatform\Filter\FilterParameters;
use Gingerminds\CoreBundle\Controller\ValueResolver\ResourceDefinitionValueResolver;
use Gingerminds\CoreBundle\Model\ResourceInterface;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;
use Gingerminds\CoreBundle\Resource\ResourceDefinition;
use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Remote choices of the resource autocomplete fields (`ResourceAutocompleteType`).
 *
 * The resource is resolved (ResourceDefinitionValueResolver) before the access check: an unknown
 * resource is a 404 for everyone, not a 403.
 */
final class AutocompleteController extends AbstractController
{
    private const int PER_PAGE = 20;

    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly FilterParameters $filterParameters,
    ) {
    }

    #[Route('/_autocomplete/{resource}', name: 'gingerminds_core_autocomplete', requirements: ['resource' => '[a-z0-9_]+'], methods: ['GET'])]
    #[IsGranted(ResourceVoter::VIEW, subject: new Expression('args["resource"].name'))]
    public function __invoke(Request $request, #[ValueResolver(ResourceDefinitionValueResolver::class)] ResourceDefinition $resource): JsonResponse
    {
        $repository = $this->doctrine->getRepository($resource->entity);

        if (!$repository instanceof RepositoryInterface) {
            throw $this->createNotFoundException(\sprintf('The resource "%s" is not searchable.', $resource->name));
        }

        $search = trim((string) $request->query->get('query', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $items = $repository->paginate(
            $this->filterParameters->fromQuery($resource->entity, [FilterParameters::SEARCH => $search]),
            $page,
            self::PER_PAGE,
        );

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
                ? $this->generateUrl('gingerminds_core_autocomplete', ['resource' => $resource->name, 'query' => $search, 'page' => $page + 1])
                : null,
        ]);
    }
}
