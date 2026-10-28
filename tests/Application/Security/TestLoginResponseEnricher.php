<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\Security;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Security\Api\LoginResponseEnricherInterface;

/**
 * AuthTest: LoginResponseEnricherInterface services extend the login response (autoconfigured).
 */
final class TestLoginResponseEnricher implements LoginResponseEnricherInterface
{
    public function enrich(UserInterface $user, array $data): array
    {
        return [...$data, 'email' => $user->getEmail()];
    }
}
