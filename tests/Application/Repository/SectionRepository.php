<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Repository\AbstractRepository;
use Gingerminds\CoreBundle\Repository\TreeRepositoryInterface;
use Gingerminds\CoreBundle\Repository\TreeRepositoryTrait;
use Gingerminds\CoreBundle\Tests\Application\Entity\Section;

/**
 * @extends AbstractRepository<Section>
 */
class SectionRepository extends AbstractRepository implements TreeRepositoryInterface
{
    use TreeRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Section::class);
    }
}
