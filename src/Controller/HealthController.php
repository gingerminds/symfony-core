<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

final class HealthController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
