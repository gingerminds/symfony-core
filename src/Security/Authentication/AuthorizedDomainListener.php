<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Authentication;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

final readonly class AuthorizedDomainListener
{
    /**
     * @param list<string> $authorizedDomains
     */
    public function __construct(
        private RequestStack $requestStack,
        private array $authorizedDomains,
    ) {
    }

    public function __invoke(CheckPassportEvent $event): void
    {
        if ([] === $this->authorizedDomains || !$event->getAuthenticator() instanceof FormLoginAuthenticator) {
            return;
        }

        $origin = $this->requestStack->getCurrentRequest()?->headers->get('origin');
        $host = null !== $origin ? parse_url($origin, \PHP_URL_HOST) : null;

        if (!\is_string($host) || !\in_array($host, $this->authorizedDomains, true)) {
            throw new CustomUserMessageAuthenticationException('security.domain_not_authorized');
        }
    }
}
