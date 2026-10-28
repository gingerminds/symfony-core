<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\User;

use Doctrine\Persistence\ObjectRepository;
use Gingerminds\CoreBundle\Entity\User\ContributorInterface;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;

/**
 * The repository of the configured contributor entity (`gingerminds_core.repository.contributor`).
 *
 * @extends RepositoryInterface<ContributorInterface>
 * @extends ObjectRepository<ContributorInterface>
 */
interface ContributorRepositoryInterface extends RepositoryInterface, ObjectRepository
{
    /**
     * Runs $write (a flush) that may move users between contributors, the contributors left
     * released first (contributors.user_id is unique).
     *
     * @template R
     *
     * @param \Closure(): R $write
     *
     * @return R
     */
    public function writeUserLinks(\Closure $write): mixed;

    /**
     * @return list<ContributorInterface>
     */
    public function findAllOrdered(): array;
}
