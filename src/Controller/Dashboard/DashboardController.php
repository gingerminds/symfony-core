<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\Dashboard;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class DashboardController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('@GingermindsCore/pages/dashboard.html.twig');
    }
}
