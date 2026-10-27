<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController
{
    public function __construct(
        private readonly AuthenticationUtils $authenticationUtils,
    ) {
    }

    #[Route('/login', name: 'gingerminds_core_login', methods: ['GET', 'POST'])]
    public function __invoke(): Response
    {
        return $this->render('@GingermindsCore/security/login.html.twig', [
            'last_username' => $this->authenticationUtils->getLastUsername(),
            'error' => $this->authenticationUtils->getLastAuthenticationError(),
        ]);
    }
}
