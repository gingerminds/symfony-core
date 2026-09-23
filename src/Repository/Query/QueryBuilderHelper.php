<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Query;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

final readonly class QueryBuilderHelper
{
    public function __construct(
        private QueryBuilder $queryBuilder,
        private string $rootAlias,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    public function getRootAlias(): string
    {
        return $this->rootAlias;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * @return ClassMetadata<object>
     */
    public function getRootMetadata(): ClassMetadata
    {
        /** @var class-string $rootEntity */
        $rootEntity = $this->queryBuilder->getRootEntities()[0];

        return $this->entityManager->getClassMetadata($rootEntity);
    }

    public function resolveField(string $path): ?string
    {
        $resolved = $this->walk($path);

        if (null === $resolved || null === $resolved['field']) {
            return null;
        }

        return $resolved['alias'] . '.' . $resolved['field'];
    }

    /**
     * @return array{metadata: ClassMetadata<object>, alias: string, name: string}|null
     */
    public function resolveOwner(string $path): ?array
    {
        $segments = explode('.', $path);
        $name = array_pop($segments);
        $alias = $this->rootAlias;
        $metadata = $this->getRootMetadata();

        foreach ($segments as $association) {
            if (!$metadata->hasAssociation($association)) {
                return null;
            }

            $alias = $this->join($alias, $association);
            $metadata = $this->entityManager->getClassMetadata($metadata->getAssociationTargetClass($association));
        }

        if (!$metadata->hasField($name) && !$metadata->hasAssociation($name)) {
            return null;
        }

        return ['metadata' => $metadata, 'alias' => $alias, 'name' => $name];
    }

    public function join(string $fromAlias, string $association, bool $select = false): string
    {
        $joinPath = $fromAlias . '.' . $association;

        /** @var array<string, list<Join>> $joinParts */
        $joinParts = $this->queryBuilder->getDQLPart('join');

        foreach ($joinParts as $joins) {
            foreach ($joins as $join) {
                if ($join->getJoin() === $joinPath) {
                    $alias = (string) $join->getAlias();

                    if ($select && !$this->isSelected($alias)) {
                        $this->queryBuilder->addSelect($alias);
                    }

                    return $alias;
                }
            }
        }

        $alias = $this->uniqueAlias($association);
        $this->queryBuilder->leftJoin($joinPath, $alias);

        if ($select) {
            $this->queryBuilder->addSelect($alias);
        }

        return $alias;
    }

    public function eagerLoad(string $path): void
    {
        $alias = $this->rootAlias;
        $metadata = $this->getRootMetadata();

        foreach (explode('.', $path) as $association) {
            if (!$metadata->hasAssociation($association)) {
                return;
            }

            $alias = $this->join($alias, $association, true);
            $metadata = $this->entityManager->getClassMetadata($metadata->getAssociationTargetClass($association));
        }
    }

    public function parameter(mixed $value, mixed $type = null): string
    {
        $name = 'gm_p' . $this->queryBuilder->getParameters()->count();
        $this->queryBuilder->setParameter($name, $value, $type);

        return ':' . $name;
    }

    /**
     * @return array{alias: string, field: ?string}|null
     */
    private function walk(string $path): ?array
    {
        $owner = $this->resolveOwner($path);

        if (null === $owner) {
            return null;
        }

        return [
            'alias' => $owner['alias'],
            'field' => $owner['metadata']->hasField($owner['name']) ? $owner['name'] : null,
        ];
    }

    private function isSelected(string $alias): bool
    {
        foreach ($this->queryBuilder->getDQLPart('select') as $select) {
            if (\in_array($alias, $select->getParts(), true)) {
                return true;
            }
        }

        return false;
    }

    private function uniqueAlias(string $association): string
    {
        $base = 'gm_' . preg_replace('/\W/', '', $association);
        $aliases = $this->queryBuilder->getAllAliases();
        $alias = $base;
        $i = 1;

        while (\in_array($alias, $aliases, true)) {
            $alias = $base . $i++;
        }

        return $alias;
    }
}
