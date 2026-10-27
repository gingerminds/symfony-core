<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\Dashboard;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'gingerminds_core_dashboard', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('@GingermindsCore/pages/dashboard.html.twig');
    }
}
