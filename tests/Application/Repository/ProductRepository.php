<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Repository\AbstractFacetRepository;
use Gingerminds\CoreBundle\Tests\Application\Entity\Product;

/**
 * @extends AbstractFacetRepository<Product>
 */
class ProductRepository extends AbstractFacetRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }
}
