<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\Security\Api;

use Gingerminds\CoreBundle\Security\Api\ApiLoginAuthenticator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AuthController
{
    public function __construct(
        private Security $security,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * The login is answered by ApiLoginAuthenticator, on the firewall of the route.
     */
    public function login(): never
    {
        throw new \LogicException(\sprintf(
            'The "%s" route is handled by the "gingerminds_core.security.api_login_authenticator" custom authenticator: '
            . 'declare it on a firewall matching the route (see docs/Installation.md).',
            ApiLoginAuthenticator::ROUTE,
        ));
    }

    /**
     * Native logout of the API firewall: LogoutEvent listeners revoke the token
     * (ApiTokenRevocationListener) and may set the response.
     */
    public function logout(): Response
    {
        if (!$this->security->getUser() instanceof UserInterface) {
            return $this->message('security.api.unauthenticated', Response::HTTP_UNAUTHORIZED);
        }

        return $this->security->logout(false) ?? $this->message('security.api.logged_out');
    }

    private function message(string $key, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(['message' => $this->translator->trans($key, [], 'security')], $status);
    }
}
