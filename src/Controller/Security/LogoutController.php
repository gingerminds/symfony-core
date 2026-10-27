<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class LogoutController extends AbstractController
{
    public function __invoke(): never
    {
        throw new \LogicException('This route is handled by the firewall "logout" key.');
    }
}
