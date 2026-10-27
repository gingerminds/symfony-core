<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Controller\AbstractCrudController;
use Gingerminds\CoreBundle\Resource\AsCrudController;
use Gingerminds\CoreBundle\Tests\Application\Entity\Category;
use Gingerminds\CoreBundle\Tests\Application\Entity\Product;
use Gingerminds\CoreBundle\Tests\Application\Form\CategoryProductType;

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
