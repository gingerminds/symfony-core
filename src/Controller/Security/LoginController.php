<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\Security;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;

final readonly class LoginController
{
    public function __construct(
        private Environment $twig,
        private AuthenticationUtils $authenticationUtils,
    ) {
    }

    public function __invoke(): Response
    {
        return new Response($this->twig->render('@GingermindsCore/security/login.html.twig', [
            'last_username' => $this->authenticationUtils->getLastUsername(),
            'error' => $this->authenticationUtils->getLastAuthenticationError(),
        ]));
    }
}
