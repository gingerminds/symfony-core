<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use Gingerminds\CoreBundle\ApiPlatform\Filter\ComputedFilterStore;
use Gingerminds\CoreBundle\ApiPlatform\State\ResourceProvider;
use Gingerminds\CoreBundle\Repository\ListQuery;
use Gingerminds\CoreBundle\Tests\Application\Entity\Product;
use Gingerminds\CoreBundle\Tests\Application\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Computes facets (published date bounds, category counts) for collections.
 *
 * @extends ResourceProvider<Product>
 */
final class ProductProvider extends ResourceProvider
{
    public function __construct(
        private readonly ProductRepository $products,
        RequestStack $requestStack,
        private readonly ComputedFilterStore $filterStore,
    ) {
        parent::__construct($products, $requestStack);
    }

    protected function configureListQuery(ListQuery $query, Operation $operation, array $uriVariables, array $context): ListQuery
    {
        if ($operation instanceof CollectionOperationInterface) {
            $counts = $this->products->getAssociationFacetCounts($query, 'category');

            $this->filterStore->set(Product::class, [
                'publishedAt' => ['type' => 'date', 'options' => $this->products->getDateFacetStats($query, 'publishedAt')],
                'category' => [
                    'type' => 'select-entity',
                    'options' => array_map(
                        static fn (int|string $id, int $total): array => ['value' => $id, 'total' => $total],
                        array_keys($counts),
                        $counts,
                    ),
                ],
            ]);
        }

        return $query;
    }
}
