<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;

/**
 * Stable pages: a collection sorted by an `order[...]` parameter ends with its identifier, as
 * the admin lists do (API Platform's OrderExtension only sorts by identifier when nothing else
 * sorts). After OrderExtension (-32), before PaginationExtension (-64).
 */
final readonly class IdentifierOrderExtension implements QueryCollectionExtensionInterface
{
    public const int PRIORITY = -40;

    /**
     * @param array<string, mixed> $context
     */
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        /** @var list<OrderBy> $orderBy */
        $orderBy = $queryBuilder->getDQLPart('orderBy');

        if ([] === $orderBy) {
            return;
        }

        $metadata = $queryBuilder->getEntityManager()->getClassMetadata($resourceClass);

        if ($metadata->containsForeignIdentifier || $metadata->isIdentifierComposite) {
            return;
        }

        $identifier = $queryBuilder->getRootAliases()[0] . '.' . $metadata->getSingleIdentifierFieldName();

        foreach ($orderBy as $order) {
            foreach ($order->getParts() as $part) {
                if (str_starts_with($part, $identifier . ' ') || $part === $identifier) {
                    return;
                }
            }
        }

        $queryBuilder->addOrderBy($identifier, 'ASC');
    }
}
