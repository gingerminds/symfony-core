<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Api;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * The API login payload lacks the "email" or "password" field (422, before any
 * user lookup or login throttling check).
 */
final class MissingApiCredentialsException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'security.api.credentials_required';
    }
}
