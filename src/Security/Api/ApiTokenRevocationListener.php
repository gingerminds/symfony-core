<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Api;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Repository\Security\ApiTokenRepositoryInterface;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Revokes the API token of a logout (`POST /api/logout`, dispatched through
 * Security::logout() on the API firewall), or every token of the user with
 * `{"revoke_all": true}`. A logout without bearer token (admin session) is ignored.
 */
final readonly class ApiTokenRevocationListener
{
    public function __construct(
        private ApiTokenRepositoryInterface $tokens,
        private AccessTokenExtractorInterface $accessTokenExtractor,
    ) {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $user = $event->getToken()?->getUser();
        $accessToken = $this->accessTokenExtractor->extractAccessToken($request);

        if (!$user instanceof UserInterface || null === $accessToken) {
            return;
        }

        try {
            $revokeAll = true === ($request->toArray()['revoke_all'] ?? false);
        } catch (\Throwable) {
            $revokeAll = false;
        }

        if ($revokeAll) {
            $this->tokens->revokeAll($user);
        } else {
            $this->tokens->revoke($accessToken);
        }
    }
}
