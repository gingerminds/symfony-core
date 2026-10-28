<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\State;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\State\ProviderInterface;
use Gingerminds\CoreBundle\ApiPlatform\Filter\ComputedFilterStore;
use Gingerminds\CoreBundle\Tests\Application\Entity\Product;
use Gingerminds\CoreBundle\Tests\Application\Repository\ProductRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The product collection of the native Doctrine provider, with its facets (published date
 * bounds, category counts) computed against the request's list parameters.
 *
 * @implements ProviderInterface<Product>
 */
final readonly class ProductProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<Product> $collectionProvider
     */
    public function __construct(
        #[Autowire(service: CollectionProvider::class)]
        private ProviderInterface $collectionProvider,
        private ProductRepository $products,
        private ComputedFilterStore $filterStore,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $parameters = $operation->getParameters() ?? new Parameters();
        $counts = $this->products->getAssociationFacetCounts($parameters, 'category');

        $this->filterStore->set(Product::class, [
            'publishedAt' => ['type' => 'date', 'options' => $this->products->getDateFacetStats($parameters, 'publishedAt')],
            'category' => [
                'type' => 'relation',
                'options' => array_map(
                    static fn (int|string $id, int $total): array => ['value' => $id, 'total' => $total],
                    array_keys($counts),
                    $counts,
                ),
            ],
        ]);

        return $this->collectionProvider->provide($operation, $uriVariables, $context);
    }
}
