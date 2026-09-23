<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\Security\Api;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Repository\Security\ApiTokenRepository;
use Gingerminds\CoreBundle\Repository\User\UserRepository;
use Gingerminds\CoreBundle\Security\Api\LoginResponseEnricherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AuthController
{
    /**
     * @param iterable<LoginResponseEnricherInterface> $enrichers
     */
    public function __construct(
        private UserRepository $users,
        private ApiTokenRepository $tokens,
        private UserPasswordHasherInterface $passwordHasher,
        private RateLimiterFactoryInterface $loginLimiter,
        private Security $security,
        private iterable $enrichers,
        private ?int $tokenTtl,
        private TranslatorInterface $translator,
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            $payload = [];
        }

        $email = \is_string($payload['email'] ?? null) ? trim($payload['email']) : '';
        $password = \is_string($payload['password'] ?? null) ? $payload['password'] : '';

        if ('' === $email || '' === $password) {
            return $this->message('security.api.credentials_required', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $limiter = $this->loginLimiter->create(mb_strtolower($email) . '|' . $request->getClientIp());

        if (!$limiter->consume()->isAccepted()) {
            return $this->message('security.api.too_many_attempts', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $user = $this->users->loadUserByIdentifier($email);

        if (!$user instanceof UserInterface || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->message('security.api.invalid_credentials', Response::HTTP_UNAUTHORIZED);
        }

        $limiter->reset();

        $expiresAt = null !== $this->tokenTtl ? new \DateTimeImmutable('+' . $this->tokenTtl . ' seconds') : null;
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

    public function logout(Request $request): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof UserInterface) {
            return $this->message('security.api.unauthenticated', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $revokeAll = true === ($request->toArray()['revoke_all'] ?? false);
        } catch (\Throwable) {
            $revokeAll = false;
        }

        if ($revokeAll) {
            $this->tokens->revokeAll($user);
        } elseif (null !== $token = $this->bearerToken($request)) {
            $this->tokens->revoke($token);
        }

        return $this->message('security.api.logged_out');
    }

    private function message(string $key, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(['message' => $this->translator->trans($key, [], 'security')], $status);
    }

    private function bearerToken(Request $request): ?string
    {
        $header = (string) $request->headers->get('Authorization', '');

        return str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : null;
    }
}
