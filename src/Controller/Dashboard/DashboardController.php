<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller\Dashboard;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class DashboardController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        return new Response($this->twig->render('@GingermindsCore/pages/dashboard.html.twig'));
    }
}
