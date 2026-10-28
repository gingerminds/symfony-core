<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Api;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Repository\Security\ApiTokenRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Issues the API token of a successful API login:
 * `{"token": "gm_...", "token_type": "Bearer", "expires_at": null|ATOM}`, extended by
 * the LoginResponseEnricherInterface services. A LoginSuccessEvent listener can still
 * replace the response afterwards.
 */
final readonly class ApiLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    /**
     * @param iterable<LoginResponseEnricherInterface> $enrichers
     */
    public function __construct(
        private ApiTokenRepositoryInterface $tokens,
        private iterable $enrichers,
        private ?int $tokenTtl,
        private ClockInterface $clock,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            throw new UnsupportedUserException(\sprintf('API tokens can only be issued to "%s" users.', UserInterface::class));
        }

        $expiresAt = null !== $this->tokenTtl
            ? \DateTimeImmutable::createFromInterface($this->clock->now())->modify('+' . $this->tokenTtl . ' seconds')
            : null;
        $data = [
            'token' => $this->tokens->createToken($user, 'api-token', $expiresAt),
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->format(\DATE_ATOM),
        ];

        foreach ($this->enrichers as $enricher) {
            $data = $enricher->enrich($user, $data);
        }

        return new JsonResponse($data);
    }
}
