<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\Security\Api;

use Gingerminds\CoreBundle\Security\Api\ApiLoginAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AuthController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * The login is answered by ApiLoginAuthenticator, on the firewall of the route.
     */
    #[Route('/login', name: ApiLoginAuthenticator::ROUTE, methods: ['POST'], format: 'json')]
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
    #[Route('/logout', name: 'gingerminds_core_api_logout', methods: ['POST'], format: 'json')]
    public function logout(): Response
    {
        if (!$this->getUser() instanceof UserInterface) {
            return $this->message('security.api.unauthenticated', Response::HTTP_UNAUTHORIZED);
        }

        return $this->security->logout(false) ?? $this->message('security.api.logged_out');
    }

    private function message(string $key, int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->json(['message' => $this->translator->trans($key, [], 'security')], $status);
    }
}
