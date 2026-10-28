<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\Controller;

use ApiPlatform\Metadata\Parameters;
use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Controller\AbstractCrudController;
use Gingerminds\CoreBundle\Resource\AsCrudController;
use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Gingerminds\CoreBundle\Tests\Application\Entity\Category;
use Gingerminds\CoreBundle\Tests\Application\Entity\Product;
use Gingerminds\CoreBundle\Tests\Application\Form\CategoryProductType;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Nested resource (AdminTest): the products of a category, `/admin/categories/{categoryId}/products`.
 */
#[AsCrudController(
    resource: 'category_product',
    entity: Product::class,
    form: CategoryProductType::class,
    path: 'categories/{categoryId}/products',
    permission: 'products',
    templatePrefix: 'admin/category_product',
)]
final class CategoryProductController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * An action of its own (RoutingTest): relative to the resource like the CRUD actions,
     * `admin_category_product_count`, `/admin/categories/{categoryId}/products/count`.
     */
    #[Route('/count', name: 'count', methods: ['GET'])]
    #[IsGranted(ResourceVoter::VIEW, subject: new Expression(self::RESOURCE_SUBJECT))]
    public function count(): JsonResponse
    {
        return $this->json([
            'resource' => $this->getResource()->name,
            'route_parameters' => $this->getRouteParameters(),
            'count' => $this->getRepository()->paginate(new Parameters())->getTotalItems(),
        ]);
    }

    protected function createEntity(): Product
    {
        $product = new Product();
        $categoryId = $this->getRouteParameters()['categoryId'] ?? null;

        if (is_numeric($categoryId)) {
            $product->setCategory($this->entityManager->find(Category::class, (int) $categoryId));
        }

        return $product;
    }
}
