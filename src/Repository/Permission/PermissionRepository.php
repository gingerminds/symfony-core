<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Permission;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Entity\Permission\PermissionInterface;
use Gingerminds\CoreBundle\Repository\AbstractRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @extends AbstractRepository<PermissionInterface>
 */
class PermissionRepository extends AbstractRepository
{
    protected int $itemsPerPage = 25;

    /**
     * @param class-string<PermissionInterface> $entityClass
     */
    public function __construct(
        ManagerRegistry $registry,
        #[Autowire(param: 'gingerminds_core.resource.permission.entity')]
        string $entityClass,
    ) {
        parent::__construct($registry, $entityClass);
    }

    public function findOneByName(string $name): ?PermissionInterface
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * @return array<string, list<PermissionInterface>>
     */
    public function findAllGrouped(): array
    {
        $grouped = [];

        foreach ($this->findBy([], ['name' => 'ASC']) as $permission) {
            $parts = explode(' ', (string) $permission->getName(), 2);
            $grouped[$parts[1] ?? 'other'][] = $permission;
        }

        ksort($grouped);

        return $grouped;
    }
}
