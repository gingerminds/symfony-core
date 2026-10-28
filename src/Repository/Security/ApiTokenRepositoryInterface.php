<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Security;

use Gingerminds\CoreBundle\Entity\Security\ApiToken;
use Gingerminds\CoreBundle\Entity\User\UserInterface;

/**
 * The API tokens (`gingerminds_core.repository.api_token`), used by the API login, logout and
 * token authentication.
 */
interface ApiTokenRepositoryInterface
{
    /**
     * Creates and stores a token; returns the plain value, only available now.
     */
    public function createToken(UserInterface $user, string $name = 'api-token', ?\DateTimeImmutable $expiresAt = null): string;

    public function findOneByPlainToken(string $plainToken): ?ApiToken;

    public function markUsed(ApiToken $token, \DateTimeImmutable $at): void;

    public function revoke(string $plainToken): void;

    public function revokeAll(UserInterface $user): void;
}
