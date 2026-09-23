<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\Security;

final class LogoutController
{
    public function __invoke(): never
    {
        throw new \LogicException('This route is handled by the firewall "logout" key.');
    }
}
