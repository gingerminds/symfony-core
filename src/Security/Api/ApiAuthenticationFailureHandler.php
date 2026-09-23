<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ApiAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        $message = $this->translator->trans($exception->getMessageKey(), $exception->getMessageData(), 'security');

        return new JsonResponse(['message' => $message], Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => \sprintf('Bearer error="invalid_token",error_description="%s"', addcslashes($message, '"\\')),
        ]);
    }
}
