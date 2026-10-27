<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

final class LogoutController extends AbstractController
{
    #[Route('/logout', name: 'gingerminds_core_logout', methods: ['GET', 'POST'])]
    public function __invoke(): never
    {
        throw new \LogicException('This route is handled by the firewall "logout" key.');
    }
}
