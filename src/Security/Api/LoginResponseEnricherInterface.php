<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Api;

use Gingerminds\CoreBundle\Entity\User\UserInterface;

interface LoginResponseEnricherInterface
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function enrich(UserInterface $user, array $data): array;
}
