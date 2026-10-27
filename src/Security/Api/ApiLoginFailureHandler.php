<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Error responses of the API login, `{"message": "..."}` translated in the `security` domain:
 * 422 missing field, 429 login throttling, 401 otherwise (unknown user and wrong password
 * alike; a user checker's CustomUserMessageAccountStatusException keeps its own message).
 */
final readonly class ApiLoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        [$key, $parameters, $status] = match (true) {
            $exception instanceof MissingApiCredentialsException => [$exception->getMessageKey(), [], Response::HTTP_UNPROCESSABLE_ENTITY],
            $exception instanceof TooManyLoginAttemptsAuthenticationException => ['security.api.too_many_attempts', [], Response::HTTP_TOO_MANY_REQUESTS],
            $exception instanceof BadCredentialsException => ['security.api.invalid_credentials', [], Response::HTTP_UNAUTHORIZED],
            default => [$exception->getMessageKey(), $exception->getMessageData(), Response::HTTP_UNAUTHORIZED],
        };

        return new JsonResponse(['message' => $this->translator->trans($key, $parameters, 'security')], $status);
    }
}
