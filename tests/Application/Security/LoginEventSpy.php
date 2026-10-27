<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * AuthTest: the native security events are the extension point of the API login/logout.
 */
final class LoginEventSpy
{
    /** @var list<string> */
    public array $logins = [];

    /** @var list<string> */
    public array $logouts = [];

    #[AsEventListener]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $this->logins[] = $event->getFirewallName() . ':' . $event->getUser()->getUserIdentifier();
    }

    #[AsEventListener]
    public function onLogout(LogoutEvent $event): void
    {
        $this->logouts[] = (string) $event->getToken()?->getUserIdentifier();
    }
}
