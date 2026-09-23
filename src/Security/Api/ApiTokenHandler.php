<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Api;

use Gingerminds\CoreBundle\Entity\Security\ApiToken;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Repository\Security\ApiTokenRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final readonly class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private ApiTokenRepository $tokens,
        private ClockInterface $clock,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $token = $this->tokens->findOneByPlainToken($accessToken);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        if (!$token instanceof ApiToken || $token->isExpired($now)) {
            // Translated in the `security` domain by ApiAuthenticationFailureHandler.
            throw new CustomUserMessageAuthenticationException('security.api.invalid_token');
        }

        // Throttled write: at most once a minute per token.
        if (!$token->getLastUsedAt() instanceof \DateTimeImmutable || $token->getLastUsedAt() < $now->modify('-1 minute')) {
            $this->tokens->markUsed($token, $now);
        }

        $user = $token->getUser();

        return new UserBadge($user->getUserIdentifier(), static fn (): UserInterface => $user);
    }
}
